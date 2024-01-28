<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Persisters;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorFactory;
use Aristek\Bundle\DynamodbBundle\ODM\Iterator\CachingIterator;
use Aristek\Bundle\DynamodbBundle\ODM\Iterator\HydratingIterator;
use Aristek\Bundle\DynamodbBundle\ODM\Iterator\Iterator;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionException;
use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Query\Query;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\DynamoDbManager;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Exception\NotSupportedException;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use BadMethodCallException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionException;
use Traversable;
use function assert;
use function count;
use function current;
use function gettype;
use function is_array;
use function is_object;
use function spl_object_hash;
use function sprintf;

/**
 * The DocumentPersister is responsible for persisting documents.
 *
 * @internal
 */
final class DocumentPersister
{
    private CollectionPersister $cp;

    private DynamoDbManager $dbManager;

    private QueryBuilder $queryBuilder;

    /**
     * @var array<string, object>
     */
    private array $queuedInserts = [];

    /**
     * @var array<string, object>
     */
    private array $queuedUpserts = [];

    public function __construct(
        private readonly PersistenceBuilder $pb,
        private readonly DocumentManager $dm,
        private readonly UnitOfWork $uow,
        private readonly HydratorFactory $hydratorFactory,
        private readonly ClassMetadata $class,
    ) {
        $this->cp = $this->uow->getCollectionPersister();

        if ($class->isEmbeddedDocument) {
            return;
        }

        $this->queryBuilder = $dm->getQueryBuilder($class->name);
        $this->dbManager = $this->queryBuilder->getDynamoDbManager();
    }

    /**
     * Adds a document to the queued insertions. The document remains queued until {@link executeInserts} is invoked.
     */
    public function addInsert(object $document): void
    {
        $this->queuedInserts[spl_object_hash($document)] = $document;
    }

    /**
     * Adds a document to the queued upserts. The document remains queued until {@link executeUpserts} is invoked.
     */
    public function addUpsert(object $document): void
    {
        $this->queuedUpserts[spl_object_hash($document)] = $document;
    }

    /**
     * @throws ReflectionException
     * @throws MappingException
     */
    public function createReferenceManyWithRepositoryMethodCursor(PersistentCollectionInterface $collection): Iterator
    {
        $mapping = $collection->getMapping();
        $repositoryMethod = $mapping['repositoryMethod'];
        $cursor = $this->dm
            ->getRepository($mapping['targetDocument'])
            ->$repositoryMethod(
                $collection->getOwner()
            );

        if (!$cursor instanceof Iterator) {
            throw new BadMethodCallException(
                sprintf('Expected repository method %s to return an iterable object', $repositoryMethod)
            );
        }

        return new CachingIterator(
            new HydratingIterator(
                $cursor,
                $this->dm->getUnitOfWork(),
                $this->dm->getClassMetadata($mapping['targetDocument'])
            )
        );
    }

    /**
     * Removes document
     */
    public function delete(object $document): void
    {
        $query = $this->getQueryForDocument($document);

        assert($this->dbManager instanceof DynamoDbManager);

        $this->dbManager->deleteOne($query, $this->getTable());
    }

    /**
     * Executes all queued document insertions.
     *
     * Queued documents without an ID will inserted in a batch and queued
     * documents with an ID will be upserted individually.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     */
    public function executeInserts(): void
    {
        if (!$this->queuedInserts) {
            return;
        }

        $inserts = [];
        foreach ($this->queuedInserts as $document) {
            $data = $this->pb->prepareInsertData($document);
            $inserts[] = $data;
        }

        try {
            assert($this->dbManager instanceof DynamoDbManager);
            $this->dbManager->insertMany($inserts, $this->getTable());
        } catch (Exception $e) {
            $this->queuedInserts = [];

            throw $e;
        }

        /* All collections except for ones using addToSet have already been
         * saved. We have left these to be handled separately to avoid checking
         * collection for uniqueness on PHP side.
         */
        foreach ($this->queuedInserts as $document) {
            $this->handleCollections($document);
        }

        $this->queuedInserts = [];
    }

    /**
     * Executes all queued document upserts.
     * Queued documents with an ID are upserted individually.
     * If no upserts are queued, invoking this method is a NOOP.
     */
    public function executeUpserts(): void
    {
        if (!$this->queuedUpserts) {
            return;
        }

        foreach ($this->queuedUpserts as $oid => $document) {
            try {
                $this->executeUpsert($document);
                $this->handleCollections($document);
                unset($this->queuedUpserts[$oid]);
            } catch (Exception $e) {
                unset($this->queuedUpserts[$oid]);

                throw $e;
            }
        }
    }

    /**
     * Checks whether the given managed document exists in the database.
     */
    public function exists(object $document): bool
    {
        $qb = $this->getNewQuery();

        return (bool) $qb->find(
            id: $this->class->getPrimaryIndexData($document),
            hydrationMode: QueryBuilder::HYDRATE_ARRAY
        );
    }

    /**
     * Gets the ClassMetadata instance of the document class this persister is used for.
     */
    public function getClassMetadata(): ClassMetadata
    {
        return $this->class;
    }

    /**
     * Finds a document by a set of criteria.
     *
     * @param array<string, mixed> $criteria Query criteria
     * @param object|null          $document
     * @param string|null          $indexName
     * @param array                $hints
     *
     * @return object|null
     */
    public function load(
        array $criteria,
        ?object $document = null,
        ?string $indexName = null,
        array $hints = []
    ): ?object {
        $qb = $this->getNewQuery();

        if ($indexName) {
            $qb->withIndex($indexName);
        }

        if (count($criteria) === 1) {
            return $qb->where($criteria)->all();
        }

        $result = $qb->find(id: $criteria, hydrationMode: QueryBuilder::HYDRATE_ARRAY);

        return $result ? $this->createDocument($result, $document, $hints) : null;
    }

    /**
     * Finds documents by a set of criteria.
     *
     * @param array<string, mixed>           $criteria
     * @param array<string, int|string>|null $sort
     *
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws ExceptionInterface
     */
    public function loadAll(
        array $criteria = [],
        ?string $indexName = null,
        ?array $sort = null,
        ?int $limit = null,
        object|null|array $after = null
    ): Iterator {
        $qb = $this->getNewQuery();

        if ($indexName) {
            $qb->withIndex($indexName);
        }

        if ($sort !== null) {
            $order = current($sort);
            $qb->sortOrderASC($order === Criteria::ASC);
        }

        if ($limit !== null) {
            $qb->limit($limit);
        }

        if ($after !== null) {
            if (is_array($after)) {
                $qb->afterKey($after);
            }

            if (is_object($after)) {
                $qb->after($after);
            }
        }

        if ($criteria) {
            $qb->where($criteria);
        }

        $baseCursor = $qb->all(hydrationMode: QueryBuilder::HYDRATE_ITERATOR);

        return $this->wrapCursor($baseCursor);
    }

    /**
     * Loads a PersistentCollection data. Used in the initialize() method.
     */
    public function loadCollection(PersistentCollectionInterface $collection): void
    {
        $mapping = $collection->getMapping();
        switch ($mapping['association']) {
            case ClassMetadata::EMBED_MANY:
                $this->loadEmbedManyCollection($collection);
                break;

            case ClassMetadata::REFERENCE_MANY:
                if (isset($mapping['repositoryMethod']) && $mapping['repositoryMethod']) {
                    $this->loadReferenceManyWithRepositoryMethod($collection);
                } else {
                    if ($mapping['isOwningSide']) {
                        $this->loadReferenceManyCollectionOwningSide($collection);
                    } else {
                        $this->loadReferenceManyCollectionInverseSide($collection);
                    }
                }

                break;
        }
    }

    /**
     * Refreshes a managed document.
     * @throws DynamoDBException
     */
    public function refresh(object $document): void
    {
        $query = $this->getQueryForDocument($document);
        $qb = $this->getNewQuery();
        $data = $qb->find(id: $query, hydrationMode: QueryBuilder::HYDRATE_ARRAY);

        if ($data === null) {
            throw DynamoDBException::cannotRefreshDocument();
        }

        $data = $this->hydratorFactory->hydrate($document, $data);
        $this->uow->setOriginalDocumentData($document, $data);
    }

    /**
     * Updates the already persisted document if it has any new changesets.
     */
    public function update(object $document): void
    {
        $update = $this->pb->prepareUpdateData($document);

        if (!empty($update)) {
            $query = $this->getQueryForDocument($document);
            assert($this->dbManager instanceof DynamoDbManager);
            $this->dbManager->updateOne($query, $update, $this->getTable());
        }

        $this->handleCollections($document);
    }

    /**
     * Creates or fills a single document object from an query result.
     *
     * @param array<string, mixed> $result   The query result.
     * @param object|null          $document The document object to fill, if any.
     * @param array                $hints    Hints for document creation.
     *
     * @return object The filled and managed document object.
     */
    private function createDocument(array $result, ?object $document = null, array $hints = []): object
    {
        if ($document !== null) {
            $hints[Query::HINT_REFRESH] = true;
            $id = $this->class->getIdentifierValue($document);
            $this->uow->registerManaged($document, $id, $result);
        }

        return $this->uow->getOrCreateDocument($this->class->name, $result, $hints, $document);
    }

    /**
     * Executes a single upsert in {@link executeUpserts}
     */
    private function executeUpsert(object $document): void
    {
        $data = $this->pb->prepareUpsertData($document);

        try {
            assert($this->dbManager instanceof DynamoDbManager);
            $this->dbManager->insertOne($data, $this->getTable());

            return;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function getNewQuery(): QueryBuilder
    {
        return $this->queryBuilder->newQuery();
    }

    /**
     * Get shard key aware query for single document.
     *
     * @return array<string, mixed>
     */
    private function getQueryForDocument(object $document): array
    {
        $id = $this->uow->getDocumentIdentifier($document);

        return $this->class->getPrimaryIndexData(
            $document::class,
            [$this->class->getHashField() => $id[0], $this->class->getRangeField() => $id[1]]
        );
    }

    private function getTable(): string
    {
        return $this->getClassMetadata()->getDatabase() ?: $this->dm->getConfiguration()->getDatabase();
    }

    private function handleCollections(object $document): void
    {
        // Collection deletions (deletions of complete collections)
        $collections = [];
        foreach ($this->uow->getScheduledCollections($document) as $coll) {
            if (!$this->uow->isCollectionScheduledForDeletion($coll)) {
                continue;
            }

            $collections[] = $coll;
        }

        if (!empty($collections)) {
            $this->cp->delete($document, $collections);
        }

        // Collection updates (deleteRows, updateRows, insertRows)
        $collections = [];
        foreach ($this->uow->getScheduledCollections($document) as $coll) {
            if (!$this->uow->isCollectionScheduledForUpdate($coll)) {
                continue;
            }

            $collections[] = $coll;
        }

        if (!empty($collections)) {
            $this->cp->update($document, $collections);
        }

        // Take new snapshots from visited collections
        foreach ($this->uow->getVisitedCollections($document) as $coll) {
            $coll->takeSnapshot();
        }
    }

    private function loadEmbedManyCollection(PersistentCollectionInterface $collection): void
    {
        $embeddedDocuments = $collection->getDynamoData();
        $mapping = $collection->getMapping();
        $owner = $collection->getOwner();

        if (!$embeddedDocuments) {
            return;
        }

        if ($owner === null) {
            throw PersistentCollectionException::ownerRequiredToLoadCollection();
        }

        foreach ($embeddedDocuments as $key => $embeddedDocument) {
            $className = $this->uow->getClassNameForAssociation($mapping);
            $embeddedMetadata = $this->dm->getClassMetadata($className);
            $embeddedDocumentObject = $embeddedMetadata->newInstance();

            if (!is_array($embeddedDocument)) {
                throw HydratorException::associationItemTypeMismatch(
                    $owner::class,
                    $mapping['name'],
                    $key,
                    'array',
                    gettype($embeddedDocument)
                );
            }

            $this->uow->setParentAssociation($embeddedDocumentObject, $mapping, $owner, $mapping['name'].'.'.$key);

            $data = $this->hydratorFactory->hydrate(
                $embeddedDocumentObject,
                $embeddedDocument,
                $collection->getHints()
            );

            $id = null;

            if ($embeddedMetadata->identifier) {
                $id = [];
                foreach ($embeddedMetadata->identifier as $item) {
                    if (!empty($data[$item])) {
                        $id[] = $data[$item];
                    }
                }
            }

            if (empty($collection->getHints()[Query::HINT_READ_ONLY])) {
                $this->uow->registerManaged($embeddedDocumentObject, $id, $data);
            }

            $collection->add($embeddedDocumentObject);
        }
    }

    /**
     * @throws NotSupportedException
     * @throws MappingException
     * @throws ExceptionInterface
     * @throws ReflectionException
     * @throws PersistentCollectionException
     * @throws HydratorException
     */
    private function loadReferenceManyCollectionInverseSide(PersistentCollectionInterface $collection): void
    {
        $mapping = $collection->getMapping();
        $owner = $collection->getOwner();

        if ($owner === null) {
            throw PersistentCollectionException::ownerRequiredToLoadCollection();
        }

        $targetClass = $this->dm->getClassMetadata($mapping['targetDocument']);
        $mappedByFieldName = $mapping['mappedBy'];
        $index = $targetClass->getGlobalSecondaryIndex($mappedByFieldName);

        if (!$index) {
            throw PersistentCollectionException::globalSecondaryIndexRequiredToLoadCollection();
        }

        $qb = $this->dm->getQueryBuilder($mapping['targetDocument']);
        $iterator = $qb
            ->where($index->hash, $index->strategy->getHash($owner))
            ->withIndex($index->name)
            ->get(hydrationMode: QueryBuilder::HYDRATE_ITERATOR);

        assert($iterator instanceof Iterator);

        $iterator = new HydratingIterator(
            $iterator,
            $this->dm->getUnitOfWork(),
            $this->dm->getClassMetadata($mapping['targetDocument'])
        );

        foreach ($iterator as $document) {
            $targetClass->setFieldValue($document, $mappedByFieldName, $owner);
            $collection->add($document);
        }
    }

    private function loadReferenceManyCollectionOwningSide(PersistentCollectionInterface $collection): void
    {
        $mapping = $collection->getMapping();
        $owner = $collection->getOwner();
        $groupedIds = [];

        if ($owner === null) {
            throw PersistentCollectionException::ownerRequiredToLoadCollection();
        }

        foreach ($collection->getDynamoData() as $key => $reference) {
            $className = $this->uow->getClassNameForAssociation($mapping);

            $identifier = $reference;
            $id = $this->dm->getClassMetadata($className)->getPHPIdentifierValue($reference);

            // create a reference to the class and id
            $reference = $this->dm->getReference($className, $id);

            $collection->add($reference);

            // only query for the referenced object if it is not already initialized or the collection is sorted
            if (!($reference instanceof GhostObjectInterface && !$reference->isProxyInitialized())) {
                continue;
            }

            $groupedIds[$className][] = $identifier;
        }

        foreach ($groupedIds as $className => $ids) {
            $class = $this->dm->getClassMetadata($className);
            $queryBuilder = $this->dm->getQueryBuilder($className);
            $criteria = [];

            [$pk, $sk] = $class->getIdentifierFieldNames();
            foreach ($ids as $id) {
                $attributes[$pk] = $id[0];
                if (!empty($id[1])) {
                    $attributes[$sk] = $id[1];
                }

                $criteria[] = $class->getPrimaryIndexData($className, $attributes);
            }

            $documents = $queryBuilder->find(id: $criteria, hydrationMode: QueryBuilder::HYDRATE_ARRAY);

            foreach ($documents as $documentData) {
                $document = $this->uow->getById([
                    $documentData[$class->getHashField()],
                    $documentData[$class->getRangeField() ?: $class->getRangeKey()],
                ],
                    $class
                );
                if ($document instanceof GhostObjectInterface && !$document->isProxyInitialized()) {
                    $data = $this->hydratorFactory->hydrate($document, $documentData);
                    $this->uow->setOriginalDocumentData($document, $data);
                }
            }
        }
    }

    private function loadReferenceManyWithRepositoryMethod(PersistentCollectionInterface $collection): void
    {
        $cursor = $this->createReferenceManyWithRepositoryMethodCursor($collection);
        $documents = $cursor->toArray();
        foreach ($documents as $obj) {
            $collection->add($obj);
        }
    }

    /**
     * Wraps the supplied base cursor in the corresponding ODM class.
     */
    private function wrapCursor(Traversable $baseCursor): Iterator
    {
        return new CachingIterator(new HydratingIterator($baseCursor, $this->dm->getUnitOfWork(), $this->class));
    }
}

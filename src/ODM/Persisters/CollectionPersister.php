<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Persisters;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\DynamoDbManager;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use Closure;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;
use function array_fill_keys;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_reverse;
use function array_values;
use function assert;
use function count;
use function end;
use function implode;
use function sort;

/**
 * The CollectionPersister is responsible for persisting collections of embedded
 * or referenced documents. When a PersistentCollection is scheduledForDeletion
 * in the UnitOfWork by calling PersistentCollection::clear() or is
 * de-referenced in the domain application code, CollectionPersister::delete()
 * will be called. When documents within the PersistentCollection are added or
 * removed, CollectionPersister::update() will be called, which may set the
 * entire collection or delete/insert individual elements, depending on the
 * mapping strategy.
 *
 * @internal
 */
final class CollectionPersister
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly PersistenceBuilder $pb,
        private readonly UnitOfWork $uow
    ) {
    }

    /**
     * Deletes a PersistentCollection instances completely from a document using $unset.
     *
     * @param object                          $parent
     * @param PersistentCollectionInterface[] $collections
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function delete(object $parent, array $collections): void
    {
        $unsetPathsMap = [];

        foreach ($collections as $collection) {
            $mapping = $collection->getMapping();

            if ($mapping['isInverseSide']) {
                continue; // ignore inverse side
            }

            [$propertyPath] = $this->getPathAndParent($collection);
            $unsetPathsMap[$propertyPath] = true;
        }

        if (empty($unsetPathsMap)) {
            return;
        }

        /** @var string[] $unsetPaths */
        $unsetPaths = array_keys($unsetPathsMap);
        $unsetPaths = array_fill_keys($this->excludeSubPaths($unsetPaths), true);
        $query = [];

        foreach ($unsetPaths as $path => $state) {
            $query[$path] = [];
        }

        $this->executeQuery($parent, $query);
    }

    /**
     * Updates a list PersistentCollection instances deleting removed rows and inserting new rows.
     *
     * @param object                          $parent
     * @param PersistentCollectionInterface[] $collections
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function update(object $parent, array $collections): void
    {
        $colls = [];

        foreach ($collections as $coll) {
            $mapping = $coll->getMapping();

            if ($mapping['isInverseSide']) {
                continue; // ignore inverse side
            }

            $colls[] = $coll;
        }

        if (empty($colls)) {
            return;
        }

        $this->deleteElements($parent, $colls);
        $this->insertElements($parent, $colls);
    }

    /**
     * Deletes removed elements from a list of PersistentCollection instances.
     *
     * This method is intended to be used with the "pushAll" and "addToSet" strategies.
     *
     * @param object                          $parent
     * @param PersistentCollectionInterface[] $collections
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    private function deleteElements(object $parent, array $collections): void
    {
        foreach ($collections as $coll) {
            $coll->initialize();
            if (!$this->uow->isCollectionScheduledForUpdate($coll)) {
                continue;
            }

            $deleteDiff = $coll->getDeleteDiff();

            if (empty($deleteDiff)) {
                continue;
            }

            [$propertyPath] = $this->getPathAndParent($coll);
            $unsetPayload[$propertyPath] = $coll;
        }

        if (!empty($unsetPayload)) {
            foreach ($unsetPayload as $propertyPath => $coll) {
                $callback = $this->getValuePrepareCallback($coll);
                $value = array_values(array_map($callback, $coll->toArray()));
                $pushAllPayload[$propertyPath] = $value;
            }

            if (!empty($pushAllPayload)) {
                $this->executeQuery($parent, $pushAllPayload);
            }
        }
    }

    /**
     * Remove from passed paths list all sub-paths.
     *
     * @param string[] $paths
     *
     * @return string[]
     */
    private function excludeSubPaths(array $paths): array
    {
        if (empty($paths)) {
            return $paths;
        }

        sort($paths);
        $uniquePaths = [$paths[0]];
        for ($i = 1, $count = count($paths); $i < $count; ++$i) {
            $lastUniquePath = end($uniquePaths);
            assert($lastUniquePath !== false);

            if (str_starts_with($paths[$i], $lastUniquePath)) {
                continue;
            }

            $uniquePaths[] = $paths[$i];
        }

        return $uniquePaths;
    }

    /**
     * Executes a query updating the given document.
     *
     * @param object               $document
     * @param array<string, mixed> $newObj
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    private function executeQuery(object $document, array $newObj): void
    {
        $className = $document::class;
        $class = $this->dm->getClassMetadata($className);
        $id = $class->getDatabaseIdentifierValue($this->uow->getDocumentIdentifier($document));
        $query = ['id' => $id];

        $this->getDynamoDbManager($className)->updateOne(
            $class->getPrimaryIndexData($className, $query),
            $newObj,
            $this->getTable($class)
        );
    }

    private function getDynamoDbManager(string $class): DynamoDbManager
    {
        return $this->dm->getQueryBuilder($class)->getDynamoDbManager();
    }

    /**
     * Gets the parent information for a given PersistentCollection. It will
     * retrieve the top-level persistent Document that the PersistentCollection
     * lives in. We can use this to issue queries when updating a
     * PersistentCollection that is multiple levels deep inside an embedded
     * document.
     *
     *     <code>
     *     list($path, $parent) = $this->getPathAndParent($coll)
     *     </code>
     *
     * @return array{string, object|null}
     */
    private function getPathAndParent(PersistentCollectionInterface $coll): array
    {
        $mapping = $coll->getMapping();
        $fields = [];
        $parent = $coll->getOwner();
        while (($association = $this->uow->getParentAssociation($parent)) !== null) {
            [$m, $owner, $field] = $association;
            if (isset($m['reference'])) {
                break;
            }

            $parent = $owner;
            $fields[] = $field;
        }

        $propertyPath = implode('.', array_reverse($fields));
        $path = $mapping['name'];
        if ($propertyPath) {
            $path = $propertyPath.'.'.$path;
        }

        return [$path, $parent];
    }

    private function getTable(ClassMetadata $classMetadata): string
    {
        return $classMetadata->getDatabase() ?: $this->dm->getConfiguration()->getDatabase();
    }

    /**
     * Return callback instance for specified collection. This callback will prepare values for query from documents
     * that collection contain.
     */
    private function getValuePrepareCallback(PersistentCollectionInterface $coll): Closure
    {
        $mapping = $coll->getMapping();
        if (isset($mapping['embedded'])) {
            return fn($v) => $this->pb->prepareEmbeddedDocumentValue($v);
        }

        return fn($v) => $this->pb->prepareReferencedDocumentValue($mapping, $v);
    }

    /**
     * Inserts new elements for a PersistentCollection instances.
     *
     * @param object                          $parent
     * @param PersistentCollectionInterface[] $collections
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    private function insertElements(object $parent, array $collections): void
    {
        $allPathCollMap = [];
        $allPaths = [];
        $diffsMap = [];

        foreach ($collections as $coll) {
            $coll->initialize();
            if (!$this->uow->isCollectionScheduledForUpdate($coll)) {
                continue;
            }

            if (empty($coll->getInsertDiff())) {
                continue;
            }

            [$propertyPath] = $this->getPathAndParent($coll);
            $diffsMap[$propertyPath] = $coll->toArray();

            $allPathCollMap[$propertyPath] = $coll;
            $allPaths[] = $propertyPath;
        }

        if (!empty($allPaths)) {
            $this->pushAllCollections($parent, $allPaths, $allPathCollMap, $diffsMap,);
        }
    }

    /**
     * Perform collections update for 'pushAll' strategy.
     *
     * @param object                                                          $parent       Parent object to which
     *                                                                                      passed collections is
     *                                                                                      belong.
     * @param string[]                                                        $collsPaths   Paths of collections that
     *                                                                                      is passed.
     * @param array<string, PersistentCollectionInterface<array-key, object>> $pathCollsMap List of collections indexed
     *                                                                                      by their paths.
     * @param array<string, array>                                            $diffsMap     List of collection diffs
     *                                                                                      indexed by collections
     *                                                                                      paths.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    private function pushAllCollections(
        object $parent,
        array $collsPaths,
        array $pathCollsMap,
        array $diffsMap,
    ): void {
        $allPaths = $this->excludeSubPaths($collsPaths);
        $allColls = array_intersect_key($pathCollsMap, array_flip($allPaths));
        $allPayload = [];
        foreach ($allColls as $propertyPath => $coll) {
            $callback = $this->getValuePrepareCallback($coll);
            $value = array_values(array_map($callback, $diffsMap[$propertyPath]));
            $allPayload[$propertyPath] = $value;
        }

        if (!empty($allPayload)) {
            $this->executeQuery($parent, $allPayload);
        }
    }
}

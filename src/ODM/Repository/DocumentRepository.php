<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Persisters\DocumentPersister;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use InvalidArgumentException;
use LogicException;
use function sprintf;

/**
 * A DocumentRepository serves as a repository for documents with generic as well as
 * business specific methods for retrieving documents.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate documents.
 */
class DocumentRepository implements ObjectRepositoryInterface, Selectable
{
    protected const MAX_WHERE_IN_AMOUNT = 100;

    protected ClassMetadata $class;

    protected DocumentManager $dm;

    protected string $documentName;

    /**
     * Initializes this instance with the specified document manager, unit of work and class metadata.
     */
    public function __construct(DocumentManager $dm, ClassMetadata $classMetadata)
    {
        $this->documentName = $classMetadata->name;
        $this->dm = $dm;
        $this->class = $classMetadata;
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return $this->getDocumentManager()->createQueryBuilder($this->documentName);
    }

    /**
     * Finds a document matching the specified identifier. Optionally a lock mode and expected version may be specified.
     */
    public function find(?PrimaryKey $id): ?object
    {
        if ($id === null) {
            return null;
        }

        if (!$id->getRange()) {
            throw new InvalidArgumentException(
                sprintf('Method "%s" require "%s" with hash and range.', __METHOD__, PrimaryKey::class)
            );
        }

        $class = $this->getClassMetadata();

        $document = $this->dm->getUnitOfWork()->tryGetById([$id->getHash(), $id->getRange()], $class);

        if ($document) {
            return $document;
        }

        [$pk, $sk] = $class->getIdentifierFieldNames($id->getName());

        $criteria = $class->getIndexData(
            $class->getIndex($id->getName()),
            $this->getClassName(),
            [$pk => $id->getHash(), $sk => $id->getRange()]
        );

        return $this->getDocumentPersister()->load(criteria: $criteria, indexName: $id->getName());
    }

    public function findAll(): array
    {
        return $this->getDocumentPersister()->loadAll()->toArray();
    }

    /**
     * Finds documents by a set of criteria.
     */
    public function findBy(
        PrimaryKey $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?object $after = null
    ): array {
        $class = $this->getClassMetadata();

        [$pk, $sk] = $class->getIdentifierFieldNames($criteria->getName());

        $attributes = [$pk => $criteria->getHash()];

        if ($criteria->getRange()) {
            $attributes[$sk] = $criteria->getRange();
        }

        return $this->getDocumentPersister()->loadAll(
            $class->getIndexData($class->getIndex($criteria->getName()), $this->getClassName(), $attributes),
            $criteria->getName(),
            $orderBy,
            $limit,
            $after
        )->toArray();
    }

    /**
     * Finds a single document by a set of criteria.
     *
     * @param PrimaryKey $criteria
     *
     * @return object|null
     */
    public function findOneBy(PrimaryKey $criteria): ?object
    {
        if ($criteria->getHash() && $criteria->getRange()) {
            return $this->find($criteria);
        }

        $items = $this->findBy($criteria);

        return reset($items);
    }

    public function getClassMetadata(): ClassMetadata
    {
        return $this->class;
    }

    public function getClassName(): string
    {
        return $this->documentName;
    }

    public function getDocumentManager(): DocumentManager
    {
        return $this->dm;
    }

    /**
     * Selects all elements from a selectable that match the expression and
     * returns a new collection containing these elements.
     *
     * @param Criteria $criteria
     *
     * @return ArrayCollection<array-key, object>
     */
    public function matching(Criteria $criteria): ArrayCollection
    {
        throw new LogicException('Not Supported.');
    }

    protected function getDocumentPersister(): DocumentPersister
    {
        return $this->dm->getUnitOfWork()->getDocumentPersister($this->documentName);
    }
}

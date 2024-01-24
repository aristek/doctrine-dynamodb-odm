<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Id\Index;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Persisters\DocumentPersister;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
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
    protected ClassMetadata $class;

    protected DocumentManager $dm;

    protected string $documentName;

    protected UnitOfWork $uow;

    /**
     * Initializes this instance with the specified document manager, unit of work and class metadata.
     */
    public function __construct(DocumentManager $dm, UnitOfWork $uow, ClassMetadata $classMetadata)
    {
        $this->documentName = $classMetadata->name;
        $this->dm = $dm;
        $this->uow = $uow;
        $this->class = $classMetadata;
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return $this->getDocumentManager()->createQueryBuilder($this->documentName);
    }

    /**
     * Finds a document matching the specified identifier. Optionally a lock mode and expected version may be specified.
     */
    public function find(?Index $id): ?object
    {
        if ($id === null) {
            return null;
        }

        if (!$id->getRange()) {
            throw new InvalidArgumentException(
                sprintf('Method "%s" require "%s" with hash and range.', __METHOD__, Index::class)
            );
        }

        $class = $this->getClassMetadata();

        $document = $this->uow->tryGetById([$id->getHash(), $id->getRange()], $class);

        if ($document) {
            return $document;
        }

        [$pk, $sk] = $class->getIdentifierFieldNames($id->getName());

        $criteria = $class->getIndexData(
            $class->getIndex($id->getName()),
            $this->getClassName(),
            [$pk => $id->getHash(), $sk => $id->getRange()]
        );

        return $this->getDocumentPersister()->load($criteria);
    }

    /**
     * Finds documents by a set of criteria.
     */
    public function findBy(
        Index $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?object $after = null
    ): array {
        $class = $this->getClassMetadata();

        [$pk, $sk] = $class->getIdentifierFieldNames($criteria->getName());

        return $this->getDocumentPersister()->loadAll(
            $class->getPrimaryIndexData(
                $this->getClassName(),
                [$pk => $criteria->getHash(), $sk => $criteria->getRange()]
            ),
            $orderBy,
            $limit,
            $offset,
            $after
        )->toArray();
    }

    /**
     * Finds a single document by a set of criteria.
     *
     * @param Index $criteria
     *
     * @return object|null
     */
    public function findOneBy(Index $criteria): ?object
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
        return $this->uow->getDocumentPersister($this->documentName);
    }
}

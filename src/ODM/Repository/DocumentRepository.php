<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Persisters\DocumentPersister;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use function is_array;

/**
 * A DocumentRepository serves as a repository for documents with generic as well as
 * business specific methods for retrieving documents.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate documents.
 */
class DocumentRepository implements ObjectRepository, Selectable
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
    public function find(mixed $id): ?object
    {
        if ($id === null) {
            return null;
        }

        $class = $this->getClassMetadata();

        [$identifierFieldName] = $class->getIdentifierFieldNames();

        if (is_array($id)) {
            if (!isset($id[$identifierFieldName])) {
                throw new InvalidArgumentException();
            }

            $id = $id[$identifierFieldName];
        }

        $document = $this->uow->tryGetById($id, $class);

        if ($document) {
            return $document;
        }

        $criteria = $class->getPrimaryIndexData($this->getClassName(), [$identifierFieldName => $id]);

        return $this->getDocumentPersister()->load($criteria);
    }

    /**
     * Finds all documents in the repository.
     */
    public function findAll(): array
    {
        return $this->findBy([]);
    }

    /**
     * Finds documents by a set of criteria.
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?object $after = null
    ): array {
        return $this->getDocumentPersister()->loadAll($criteria, $orderBy, $limit, $offset, $after)->toArray();
    }

    /**
     * Finds a single document by a set of criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws DynamoDBException
     */
    public function findOneBy(array $criteria): ?object
    {
        $class = $this->getClassMetadata();
        [$identifierFieldName] = $class->getIdentifierFieldNames();

        if (!isset($criteria[$identifierFieldName])) {
            throw new InvalidArgumentException();
        }

        $id = $criteria[$identifierFieldName];

        // Check identity map first
        $document = $this->uow->tryGetById($id, $class);

        if ($document) {
            return $document;
        }

        $criteria = $class->getPrimaryIndexData($this->getClassName(), [$identifierFieldName => $id]);

        return $this->getDocumentPersister()->load($criteria);
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

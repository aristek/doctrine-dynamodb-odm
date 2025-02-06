<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM;

use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorFactory;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadataFactory;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\Proxy\Factory\ProxyFactory;
use Aristek\Bundle\DynamodbBundle\ODM\Proxy\Factory\StaticProxyFactory;
use Aristek\Bundle\DynamodbBundle\ODM\Proxy\Resolver\CachingClassNameResolver;
use Aristek\Bundle\DynamodbBundle\ODM\Proxy\Resolver\ProxyManagerClassNameResolver;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\DocumentRepository;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\ObjectRepositoryInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\RepositoryFactory;
use Doctrine\Common\EventManager;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Persistence\Mapping\ProxyClassNameResolver;
use Doctrine\Persistence\ObjectManager;
use InvalidArgumentException;
use ReflectionException;
use RuntimeException;
use function assert;
use function gettype;
use function is_object;
use function ltrim;
use function sprintf;

/**
 * The DocumentManager class is the central access point for managing the
 * persistence of documents.
 *
 *     <?php
 *
 *     $config = new Configuration();
 *     $dm = DocumentManager::create(new Connection(), $config);
 */
class DocumentManager implements ObjectManager
{
    private ProxyClassNameResolver $classNameResolver;

    /**
     * Whether the DocumentManager is closed or not.
     */
    private bool $closed = false;

    /**
     * The used Configuration.
     */
    private Configuration $config;

    /**
     * The event manager that is the central point of the event system.
     */
    private EventManager $eventManager;

    /**
     * The Hydrator factory instance.
     */
    private HydratorFactory $hydratorFactory;

    /**
     * The metadata factory, used to retrieve the ODM metadata of document classes.
     */
    private ClassMetadataFactory $metadataFactory;

    /**
     * The Proxy factory instance.
     */
    private ProxyFactory $proxyFactory;

    /**
     * @var QueryBuilder[]
     */
    private array $queryBuilders;

    /**
     * The repository factory used to create dynamic repositories.
     */
    private RepositoryFactory $repositoryFactory;

    /**
     * The UnitOfWork used to coordinate object-level transactions.
     */
    private UnitOfWork $unitOfWork;

    /**
     * Creates a new Document that operates on the given DynamoDB connection and uses the given Configuration.
     *
     * @throws HydratorException
     */
    public static function create(?Configuration $config = null, ?EventManager $eventManager = null): DocumentManager
    {
        return new static($config, $eventManager);
    }

    /**
     * Clears the DocumentManager.
     *
     * All documents that are currently managed by this DocumentManager become detached.
     */
    public function clear(): void
    {
        $this->unitOfWork->clear();
    }

    /**
     * Closes the DocumentManager. All documents that are currently managed
     * by this DocumentManager become detached. The DocumentManager may no longer be used after it is closed.
     */
    public function close(): void
    {
        $this->clear();
        $this->closed = true;
    }

    /**
     * Determines whether a document instance is managed in this DocumentManager.
     *
     * @return bool TRUE if this DocumentManager currently manages the given document, FALSE otherwise.
     *
     * @throws InvalidArgumentException When the $object param is not an object.
     */
    public function contains(object $object): bool
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(gettype($object));
        }

        return $this->unitOfWork->isScheduledForInsert($object)
            || ($this->unitOfWork->isInIdentityMap($object) && !$this->unitOfWork->isScheduledForDelete($object));
    }

    /**
     * Create a new QueryBuilder instance for a class.
     */
    public function createQueryBuilder(string $documentName): QueryBuilder
    {
        return new QueryBuilder($this, $documentName);
    }

    /**
     * Returns a reference to the supplied document.
     *
     * @return mixed The reference for the document in question, according to the desired mapping
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function createReference(object $document, array $referenceMapping): mixed
    {
        $class = $this->getClassMetadata($document::class);
        $id = $this->unitOfWork->getDocumentIdentifier($document);

        if ($id === null) {
            throw new RuntimeException(
                sprintf(
                    'Cannot create a DBRef for class %s without an identifier.
                    Have you forgotten to persist/merge the document first?',
                    $class->name
                ),
            );
        }

        return $id;
    }

    /**
     * Detaches a document from the DocumentManager, causing a managed document to
     * become detached. Unflushed changes made to the document if any
     * (including removal of the document), will not be synchronized to the database.
     * Documents which previously referenced the detached document will continue to
     * reference it.
     *
     * @throws InvalidArgumentException When the $object param is not an object.
     */
    public function detach(object $object): void
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(gettype($object));
        }

        $this->unitOfWork->detach($object);
    }

    /**
     * Finds a Document by its identifier.
     *
     * This is just a convenient shortcut for getRepository($documentName)->find($id).
     *
     * @param mixed $id
     */
    public function find(string $className, $id): ?object
    {
        $repository = $this->getRepository($className);
        if ($repository instanceof DocumentRepository) {
            return $repository->find($id);
        }

        return $repository->find($id);
    }

    /**
     * Flushes all changes to objects that have been queued up to now to the database.
     * This effectively synchronizes the in-memory state of managed objects with the
     * database.
     *
     * @throws DynamoDBException
     * @throws MappingException
     */
    public function flush(): void
    {
        $this->errorIfClosed();
        $this->unitOfWork->commit();
    }

    /**
     * Returns the metadata for a class.
     *
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws ReflectionException
     */
    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->metadataFactory->getMetadataFor($className);
    }

    /**
     * Gets the Configuration used by the DocumentManager.
     */
    public function getConfiguration(): Configuration
    {
        return $this->config;
    }

    /**
     * Gets the EventManager used by the DocumentManager.
     */
    public function getEventManager(): EventManager
    {
        return $this->eventManager;
    }

    /**
     * Gets the Hydrator factory used by the DocumentManager to generate and get hydrators for each type of document.
     */
    public function getHydratorFactory(): HydratorFactory
    {
        return $this->hydratorFactory;
    }

    /**
     * Gets the metadata factory used to gather the metadata of classes.
     *
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory(): ClassMetadataFactory
    {
        return $this->metadataFactory;
    }

    /**
     * Gets a partial reference to the document identified by the given type and identifier
     * without actually loading it, if the document is not yet loaded.
     *
     * The returned reference may be a partial object if the document is not yet loaded/managed.
     * If it is a partial object it will not initialize the rest of the document state on access.
     * Thus you can only ever safely access the identifier of a document obtained through
     * this method.
     *
     * The use-cases for partial references involve maintaining bidirectional associations
     * without loading one side of the association or to update a document without loading it.
     * Note, however, that in the latter case the original (persistent) document data will
     * never be visible to the application (especially not event listeners) as it will
     * never be loaded in the first place.
     *
     * @throws ExceptionInterface
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function getPartialReference(string $documentName, mixed $identifier): object
    {
        $class = $this->metadataFactory->getMetadataFor(ltrim($documentName, '\\'));

        $document = $this->unitOfWork->tryGetById($identifier, $class);

        // Check identity map first, if its already in there just return it.
        if ($document) {
            return $document;
        }

        $document = $class->newInstance();
        $class->setIdentifierValue($document, $identifier);
        $this->unitOfWork->registerManaged($document, $identifier, []);

        return $document;
    }

    /**
     * Gets the proxy factory used by the DocumentManager to create document proxies.
     */
    public function getProxyFactory(): ProxyFactory
    {
        return $this->proxyFactory;
    }

    /**
     * Returns the collection instance for a class.
     */
    public function getQueryBuilder(string $className): QueryBuilder
    {
        if (!isset($this->queryBuilders[$className])) {
            $this->queryBuilders[$className] = $this->createQueryBuilder($className);
        }

        return $this->queryBuilders[$className];
    }

    /**
     * Gets a reference to the document identified by the given type and identifier
     * without actually loading it.
     *
     * If partial objects are allowed, this method will return a partial object that only
     * has its identifier populated. Otherwise a proxy is returned that automatically
     * loads itself on first access.
     *
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function getReference(string $documentName, mixed $identifier): object
    {
        $class = $this->metadataFactory->getMetadataFor(ltrim($documentName, '\\'));

        assert($class instanceof ClassMetadata);

        $document = $this->unitOfWork->tryGetById($identifier, $class);

        // Check identity map first, if its already in there just return it.
        if ($document !== false) {
            return $document;
        }

        $document = $this->proxyFactory->getProxy($class, $identifier);
        $this->unitOfWork->registerManaged($document, $identifier, []);

        return $document;
    }

    /**
     * Gets the repository for a document class.
     */
    public function getRepository(string $className): ObjectRepositoryInterface|DocumentRepository
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    /**
     * Gets the UnitOfWork used by the DocumentManager to coordinate operations.
     */
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * This method is a no-op for other objects.
     */
    public function initializeObject(object $obj): void
    {
        $this->unitOfWork->initializeObject($obj);
    }

    /**
     * Check if the Document manager is open or closed.
     */
    public function isOpen(): bool
    {
        return !$this->closed;
    }

    /**
     * Checks if a value is an uninitialized document.
     */
    public function isUninitializedObject(mixed $value): bool
    {
        return $this->unitOfWork->isUninitializedObject($value);
    }

    /**
     * Merges the state of a detached document into the persistence context
     * of this DocumentManager and returns the managed copy of the document.
     * The document passed to merge will not become associated/managed with this DocumentManager.
     *
     * @param object $object The detached document to merge into the persistence context.
     *
     * @return object The managed copy of the document.
     *
     * @throws InvalidArgumentException If the $object param is not an object.
     * @throws DynamoDBException
     */
    public function merge(object $object): object
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(gettype($object));
        }

        $this->errorIfClosed();

        return $this->unitOfWork->merge($object);
    }

    /**
     * Tells the DocumentManager to make an instance managed and persistent.
     *
     * The document will be entered into the database at or before transaction
     * commit or as a result of the flush operation.
     *
     * NOTE: The persist operation always considers documents that are not yet known to
     * this DocumentManager as NEW. Do not pass detached documents to the persist operation.
     *
     * @throws InvalidArgumentException When the given $object param is not an object.
     * @throws DynamoDBException
     */
    public function persist(object $object): void
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(gettype($object));
        }

        $this->errorIfClosed();
        $this->unitOfWork->persist($object);
    }

    /**
     * Refreshes the persistent state of a document from the database,
     * overriding any local changes that have not yet been persisted.
     *
     * @throws DynamoDBException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function refresh(object $object): void
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(gettype($object));
        }

        $this->errorIfClosed();
        $this->unitOfWork->refresh($object);
    }

    /**
     * Removes a document instance.
     *
     * A removed document will be removed from the database at or before transaction commit
     * or as a result of the flush operation.
     *
     * @throws InvalidArgumentException When the $object param is not an object.
     * @throws DynamoDBException
     */
    public function remove(object $object): void
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(gettype($object));
        }

        $this->errorIfClosed();
        $this->unitOfWork->remove($object);
    }

    /**
     * @throws HydratorException
     */
    protected function __construct(?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->config = $config ?: new Configuration();
        $this->eventManager = $eventManager ?: new EventManager();

        $metadataFactoryClassName = $this->config->getClassMetadataFactoryName();
        $this->metadataFactory = new $metadataFactoryClassName();
        $this->metadataFactory->setDocumentManager($this);
        $this->metadataFactory->setConfiguration($this->config);

        $cacheDriver = $this->config->getMetadataCache();
        if ($cacheDriver) {
            $this->metadataFactory->setCache($cacheDriver);
        }

        $hydratorDir = $this->config->getHydratorDir();
        $hydratorNs = $this->config->getHydratorNamespace();
        $this->hydratorFactory = new HydratorFactory(
            $this,
            $this->eventManager,
            $hydratorDir,
            $hydratorNs,
            $this->config->getAutoGenerateHydratorClasses(),
        );

        $this->unitOfWork = new UnitOfWork($this, $this->eventManager, $this->hydratorFactory);
        $this->hydratorFactory->setUnitOfWork($this->unitOfWork);
        $this->proxyFactory = new StaticProxyFactory($this);
        $this->repositoryFactory = $this->config->getRepositoryFactory();
        $this->classNameResolver = new CachingClassNameResolver(new ProxyManagerClassNameResolver($this->config));

        $this->metadataFactory->setProxyClassNameResolver($this->classNameResolver);
    }

    /**
     * Throws an exception if the DocumentManager is closed or currently not active.
     *
     * @throws DynamoDBException If the DocumentManager is closed.
     */
    private function errorIfClosed(): void
    {
        if ($this->closed) {
            throw DynamoDBException::documentManagerClosed();
        }
    }
}

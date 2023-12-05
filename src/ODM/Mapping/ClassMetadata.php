<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping;

use BadMethodCallException;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Instantiator\Instantiator;
use Doctrine\Instantiator\InstantiatorInterface;
use Doctrine\Persistence\Mapping\ClassMetadata as BaseClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\Persistence\Reflection\EnumReflectionProperty;
use InvalidArgumentException;
use LogicException;
use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Aristek\Bundle\DynamodbBundle\ODM\Id\IdGenerator;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Index;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\IndexStrategy;
use Aristek\Bundle\DynamodbBundle\ODM\Types\Type;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function assert;
use function class_exists;
use function count;
use function enum_exists;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function ltrim;
use function sprintf;
use const PHP_VERSION_ID;

final class ClassMetadata implements BaseClassMetadata
{
    /**
     * DEFERRED_EXPLICIT means that changes of entities are calculated at commit-time
     * by doing a property-by-property comparison with the original data. This will
     * be done only for entities that were explicitly saved (through persist() or a cascade).
     */
    public const CHANGETRACKING_DEFERRED_EXPLICIT = 2;
    /**
     * DEFERRED_IMPLICIT means that changes of entities are calculated at commit-time
     * by doing a property-by-property comparison with the original data. This will
     * be done for all entities that are in MANAGED state at commit-time.
     *
     * This is the default change tracking policy.
     */
    public const CHANGETRACKING_DEFERRED_IMPLICIT = 1;
    public const EMBED_MANY = 4;
    public const EMBED_ONE = 3;
    /**
     * NONE means Doctrine will not generate any id for us and you are responsible for manually
     * assigning an id.
     */
    public const GENERATOR_TYPE_NONE = 2;
    /**
     * UUID means Doctrine will generate uuid for us.
     */
    public const GENERATOR_TYPE_UUID = 1;
    /**
     * Indexing Types
     */
    public const INDEX_GSI = 'gsi';
    public const INDEX_LSI = 'lsi';
    public const INDEX_PRIMARY = 'primary';
    /**
     * Mapping types
     */
    public const MANY = 'many';
    public const ONE = 'one';
    /**
     * Association types
     */
    public const REFERENCE_MANY = 2;
    public const REFERENCE_ONE = 1;
    /**
     * INCREMENT means that fields will be written to the database by calculating
     * the difference and using the $inc operator
     */
    public const STORAGE_STRATEGY_PUSH_ALL = 'pushAll';

    /**
     * READ-ONLY: Array of fields to also load with a given method.
     *
     * @var array<string, array>
     */
    public array $alsoLoadMethods = [];

    /**
     * READ-ONLY: The association mappings of the class.
     * Keys are field names and values are mapping definitions.
     *
     * @var array<string, mixed>
     */
    public array $associationMappings = [];

    /**
     * READ-ONLY: The policy used for change-tracking on entities of this class.
     */
    public int $changeTrackingPolicy = self::CHANGETRACKING_DEFERRED_IMPLICIT;

    /**
     * The name of the custom repository class used for the document class.
     * (Optional).
     */
    public ?string $customRepositoryClassName = null;

    /**
     * READ-ONLY: The name of the database the document is mapped to.
     */
    public ?string $db = null;

    /**
     * READ-ONLY: The field mappings of the class.
     * Keys are field names and values are mapping definitions.
     *
     * The mapping definition array has the following values:
     *
     * - <b>fieldName</b> (string)
     * The name of the field in the Document.
     *
     * - <b>id</b> (boolean, optional)
     * Marks the field as the primary key of the document. Multiple fields of an
     * document can have the id attribute, forming a composite key.
     *
     * @var array<string, mixed>
     */
    public array $fieldMappings = [];

    /**
     * READ-ONLY: The Id generator options.
     *
     * @var array<string, mixed>
     */
    public array $generatorOptions = [];

    /**
     * READ-ONLY: The Id generator type used by the class.
     */
    public int $generatorType = self::GENERATOR_TYPE_UUID;

    /**
     * READ-ONLY: The ID generator used for generating IDs for this class.
     */
    public ?IdGenerator $idGenerator = null;

    /**
     * READ-ONLY: The field name of the document identifier.
     */
    public mixed $identifier = null;

    /**
     * READ-ONLY: The array of indexes for the document collection.
     *
     * @var array<string, array<Index>>|array<string, Index>
     */
    public array $indexes = [];

    /**
     * READ-ONLY: Whether this class describes the mapping of a embedded document.
     */
    public bool $isEmbeddedDocument = false;

    /**
     * READ-ONLY: Whether this class describes the mapping of a mapped superclass.
     */
    public bool $isMappedSuperclass = false;

    /**
     * READ_ONLY: A flag for whether or not this document is read-only.
     */
    public bool $isReadOnly = false;

    /**
     * READ-ONLY: The registered lifecycle callbacks for documents of this class.
     *
     * @var array<string, list<string>>
     */
    public array $lifecycleCallbacks = [];

    /**
     * READ-ONLY: The name of the document class.
     */
    public string $name;

    /**
     * READ-ONLY: The names of the parent classes (ancestors).
     */
    public array $parentClasses = [];

    /**
     * READ-ONLY Describes how MongoDB clients route read operations to the members of a replica set.
     */
    public ?string $readPreference = null;

    /**
     * READ-ONLY Associated with readPreference Allows to specify criteria so that your application can target read
     * operations to specific members, based on custom parameters.
     *
     * @var array<array<string, string>>
     */
    public array $readPreferenceTags = [];

    /**
     * The ReflectionClass instance of the mapped class.
     */
    public ReflectionClass $reflClass;

    /**
     * The ReflectionProperty instances of the mapped class.
     *
     * @var ReflectionProperty[]
     */
    public array $reflFields = [];

    /**
     * READ-ONLY: The name of the document class that is at the root of the mapped document inheritance
     * hierarchy. If the document is not part of a mapped inheritance hierarchy this is the same
     * as {@link $documentName}.
     */
    public string $rootDocumentName;

    /**
     * READ-ONLY: The names of all subclasses (descendants).
     */
    public array $subClasses = [];

    private InstantiatorInterface $instantiator;

    private ReflectionService $reflectionService;

    /**
     * Initializes a new ClassMetadata instance that will hold the object-document mapping
     * metadata of the class with the given name.
     *
     * @throws ReflectionException
     */
    public function __construct(string $documentName)
    {
        $this->name = $documentName;
        $this->rootDocumentName = $documentName;
        $this->reflectionService = new RuntimeReflectionService();
        $this->reflClass = new ReflectionClass($documentName);
        $this->instantiator = new Instantiator();
    }

    /**
     * Determines which fields get serialized.
     *
     * It is only serialized what is necessary for best unserialization performance.
     * That means any metadata properties that are not set or empty or simply have
     * their default value are NOT serialized.
     *
     * Parts that are also NOT serialized because they can not be properly unserialized:
     *      - reflClass (ReflectionClass)
     *      - reflFields (ReflectionProperty array)
     */
    public function __sleep(): array
    {
        // This metadata is always serialized/cached.
        $serialized = [
            'fieldMappings',
            'associationMappings',
            'identifier',
            'name',
            'db',
            'collection',
            'readPreference',
            'readPreferenceTags',
            'rootDocumentName',
            'generatorType',
            'generatorOptions',
            'idGenerator',
            'indexes',
        ];

        // The rest of the metadata is only serialized if necessary.
        if ($this->changeTrackingPolicy !== self::CHANGETRACKING_DEFERRED_IMPLICIT) {
            $serialized[] = 'changeTrackingPolicy';
        }

        if ($this->customRepositoryClassName) {
            $serialized[] = 'customRepositoryClassName';
        }

        if ($this->isMappedSuperclass) {
            $serialized[] = 'isMappedSuperclass';
        }

        if ($this->isEmbeddedDocument) {
            $serialized[] = 'isEmbeddedDocument';
        }

        if ($this->lifecycleCallbacks) {
            $serialized[] = 'lifecycleCallbacks';
        }

        if ($this->isReadOnly) {
            $serialized[] = 'isReadOnly';
        }

        return $serialized;
    }

    /**
     * Restores some state that can not be serialized/unserialized.
     *
     * @throws ReflectionException
     */
    public function __wakeup()
    {
        // Restore ReflectionClass and properties
        $this->reflectionService = new RuntimeReflectionService();
        $this->reflClass = new ReflectionClass($this->name);
        $this->instantiator = new Instantiator();

        foreach ($this->fieldMappings as $field => $mapping) {
            $prop = $this->reflectionService->getAccessibleProperty($mapping['declared'] ?? $this->name, $field);
            assert($prop instanceof ReflectionProperty);

            if (isset($mapping['enumType'])) {
                $prop = new EnumReflectionProperty($prop, $mapping['enumType']);
            }

            $this->reflFields[$field] = $prop;
        }
    }

    /**
     * Add a index for this Document.
     */
    public function addIndex(Index $index, string $type = null): void
    {
        if (!$type) {
            $this->indexes[self::INDEX_PRIMARY] = $index;
        } else {
            $this->indexes[$type][$index->name] = $index;
        }
    }

    /**
     * Adds an association mapping without completing/validating it.
     * This is mainly used to add inherited association mappings to derived classes.
     *
     * @internal
     *
     */
    public function addInheritedAssociationMapping(array $mapping): void
    {
        $this->associationMappings[$mapping['fieldName']] = $mapping;
    }

    /**
     * Adds a field mapping without completing/validating it.
     * This is mainly used to add inherited field mappings to derived classes.
     *
     * @internal
     */
    public function addInheritedFieldMapping(array $fieldMapping): void
    {
        $this->fieldMappings[$fieldMapping['fieldName']] = $fieldMapping;

        if (!isset($fieldMapping['association'])) {
            return;
        }

        $this->associationMappings[$fieldMapping['fieldName']] = $fieldMapping;
    }

    /**
     * Adds a lifecycle callback for documents of this class.
     *
     * If the callback is already registered, this is a NOOP.
     */
    public function addLifecycleCallback(string $callback, string $event): void
    {
        if (isset($this->lifecycleCallbacks[$event]) && in_array($callback, $this->lifecycleCallbacks[$event])) {
            return;
        }

        $this->lifecycleCallbacks[$event][] = $callback;
    }

    /**
     * Retrieve the collectionClass associated with an association
     */
    public function getAssociationCollectionClass(string $assocName): string
    {
        if (!isset($this->associationMappings[$assocName])) {
            throw new InvalidArgumentException("Association name expected, '".$assocName."' is not an association.");
        }

        if (!array_key_exists('collectionClass', $this->associationMappings[$assocName])) {
            throw new InvalidArgumentException(
                "collectionClass can only be applied to 'embedMany' and 'referenceMany' associations."
            );
        }

        return $this->associationMappings[$assocName]['collectionClass'];
    }

    public function getAssociationMappedByTargetField(string $assocName): void
    {
        throw new BadMethodCallException(__METHOD__.'() is not implemented yet.');
    }

    public function getAssociationNames(): array
    {
        return array_keys($this->associationMappings);
    }

    public function getAssociationTargetClass(string $assocName): ?string
    {
        if (!isset($this->associationMappings[$assocName])) {
            throw new InvalidArgumentException("Association name expected, '".$assocName."' is not an association.");
        }

        return $this->associationMappings[$assocName]['targetDocument'] ?? null;
    }

    /**
     * Returns the database this Document is mapped to.
     */
    public function getDatabase(): ?string
    {
        return $this->db;
    }

    /**
     * Casts the identifier to its database type.
     */
    public function getDatabaseIdentifierValue(mixed $id): mixed
    {
        $idType = $this->fieldMappings[$this->identifier]['type'];

        return Type::getType($idType)->convertToDatabaseValue($id);
    }

    /**
     * Gets mappings of fields holding embedded document(s).
     */
    public function getEmbeddedFieldsMappings(): array
    {
        return array_filter(
            $this->associationMappings,
            static fn($assoc) => !empty($assoc['embedded'])
        );
    }

    /**
     * Gets the mapping of a field.
     *
     * @throws MappingException If the $fieldName is not found in the fieldMappings array.
     */
    public function getFieldMapping(string $fieldName): array
    {
        if (!isset($this->fieldMappings[$fieldName])) {
            throw MappingException::mappingNotFound($this->name, $fieldName);
        }

        return $this->fieldMappings[$fieldName];
    }

    /**
     * Gets the field mapping by its DB name.
     *
     * @throws MappingException
     */
    public function getFieldMappingByDbFieldName(string $dbFieldName): array
    {
        foreach ($this->fieldMappings as $mapping) {
            if ($mapping['name'] === $dbFieldName) {
                return $mapping;
            }
        }

        throw MappingException::mappingNotFoundByDbName($this->name, $dbFieldName);
    }

    public function getFieldNames(): array
    {
        return array_keys($this->fieldMappings);
    }

    /**
     * Gets the specified field's value off the given document.
     */
    public function getFieldValue(object $document, string $field): mixed
    {
        if ($document instanceof GhostObjectInterface
            && $field !== $this->identifier
            && !$document->isProxyInitialized()
        ) {
            $document->initializeProxy();
        }

        return $this->reflFields[$field]->getValue($document);
    }

    public function getGlobalSecondaryIndex(string $name): ?Index
    {
        return $this->getGlobalSecondaryIndexes()[$name] ?? null;
    }

    public function getGlobalSecondaryIndexData(object|string $document, array $attributes = []): array
    {
        $data = [];
        foreach ($this->getGlobalSecondaryIndexes() as $name => $index) {
            if (isset($this->fieldMappings[$name], $this->reflFields[$name]) && is_object($document)) {
                $document = $this->reflFields[$name]->getValue($document);
            }

            $data[$index->hash] = $index->strategy->getHash($document, $attributes);

            if ($index->range) {
                $data[$index->range] = $index->strategy->getRange($document, $attributes);
            }
        }

        return $data;
    }

    /**
     * @return Index[]
     */
    public function getGlobalSecondaryIndexes(): array
    {
        return $this->indexes[self::INDEX_GSI] ?? [];
    }

    public function getIdentifier(): mixed
    {
        return $this->identifier;
    }

    /**
     * Sets the mapped identifier field of this class.
     *
     * @internal
     */
    public function setIdentifier(?string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getIdentifierFieldNames(): array
    {
        return [$this->identifier];
    }

    /**
     * Get the document identifier object as a database type.
     */
    public function getIdentifierObject(object $document): mixed
    {
        return $this->getDatabaseIdentifierValue($this->getIdentifierValue($document));
    }

    /**
     * Gets the document identifier as a PHP type.
     */
    public function getIdentifierValue(object $document): mixed
    {
        return $this->reflFields[$this->identifier]->getValue($document);
    }

    /**
     * Since MongoDB only allows exactly one identifier field this is a proxy
     * to {@see getIdentifierValue()} and returns an array with the identifier
     * field as a key.
     */
    public function getIdentifierValues(object $object): mixed
    {
        return $this->getIdentifierValue($object);
    }

    /**
     * Returns the array of indexes for this Document.
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getIndexesData(object $document, array $attributes = []): array
    {
        return array_merge(
            $this->getPrimaryIndexData($document, $attributes),
            $this->getGlobalSecondaryIndexData($document, $attributes),
            $this->getLocalSecondaryIndexData($document, $attributes),
        );
    }

    public function getIndexesNames(): array
    {
        $keys = static function (Index $index): array {
            $ret = [
                $index->hash,
            ];

            if ($index->range) {
                $ret[] = $index->range;
            }

            return $ret;
        };

        $keyNames = [];
        if ($this->getGlobalSecondaryIndexes()) {
            foreach ($this->getGlobalSecondaryIndexes() as $globalSecondaryIndex) {
                $keyNames += $keys($globalSecondaryIndex);
            }
        } else {
            $keyNames = $keys($this->getPrimaryIndex());
        }

        return $keyNames;
    }

    /**
     * Gets the registered lifecycle callbacks for an event.
     *
     * @return list<string>
     */
    public function getLifecycleCallbacks(string $event): array
    {
        return $this->lifecycleCallbacks[$event] ?? [];
    }

    /**
     * Sets the lifecycle callbacks for documents of this class.
     *
     * Any previously registered callbacks are overwritten.
     *
     * @param array<string, list<string>> $callbacks
     */
    public function setLifecycleCallbacks(array $callbacks): void
    {
        $this->lifecycleCallbacks = $callbacks;
    }

    public function getLocalSecondaryIndexData(object|string $document, array $attributes = []): array
    {
        $data = [];
        foreach ($this->getLocalSecondaryIndexes() as $index) {
            $data[$index->hash] = $index->strategy->getHash($document, $attributes);

            if ($index->range) {
                $data[$index->range] = $index->strategy->getRange($document, $attributes);
            }
        }

        return $data;
    }

    /**
     * @return Index[]
     */
    public function getLocalSecondaryIndexes(): array
    {
        return $this->indexes[self::INDEX_LSI] ?? [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Casts the identifier to its portable PHP type.
     */
    public function getPHPIdentifierValue(mixed $id): mixed
    {
        $idType = $this->fieldMappings[$this->identifier]['type'];

        return Type::getType($idType)->convertToPHPValue($id);
    }

    public function getPrimaryIndex(): ?Index
    {
        return $this->indexes[self::INDEX_PRIMARY] ?? null;
    }

    public function getPrimaryIndexData(object|string $document, array $attributes = []): array
    {
        $data = [];

        if ($primaryIndex = $this->getPrimaryIndex()) {
            $data[$primaryIndex->hash] = $primaryIndex->strategy->getHash($document, $attributes);
            $data[$primaryIndex->range] = $primaryIndex->strategy->getRange($document, $attributes);
        }

        return $data;
    }

    /**
     * @throws ReflectionException
     */
    public function getPropertyValue($entity, $propertyName): mixed
    {
        if (!$this->getReflectionClass()->hasProperty($propertyName)) {
            throw new LogicException(
                'Object '.$this->name.' doesn\'t have a property named: '.$propertyName
            );
        }

        $reflectionProperty = $this->getReflectionClass()->getProperty($propertyName);
        $oldAccessibility = $reflectionProperty->isPublic();
        $reflectionProperty->setAccessible(true);
        $ret = $reflectionProperty->getValue($entity);
        $reflectionProperty->setAccessible($oldAccessibility);

        return $ret;
    }

    public function getReflectionClass(): ReflectionClass
    {
        return $this->reflClass;
    }

    /**
     * Gets the ReflectionProperties of the mapped class.
     *
     * @return ReflectionProperty[]
     */
    public function getReflectionProperties(): array
    {
        return $this->reflFields;
    }

    /**
     * Gets a ReflectionProperty for a specific field of the mapped class.
     */
    public function getReflectionProperty(string $name): ReflectionProperty
    {
        return $this->reflFields[$name];
    }

    public function getTypeOfField(string $fieldName): ?string
    {
        return isset($this->fieldMappings[$fieldName]) ?
            $this->fieldMappings[$fieldName]['type'] : null;
    }

    /**
     * Checks whether the class has a mapped association (embed or reference) with the given field name.
     */
    public function hasAssociation(string $fieldName): bool
    {
        return $this->hasReference($fieldName) || $this->hasEmbed($fieldName);
    }

    /**
     * Checks whether the class has a mapped embed with the given field name.
     */
    public function hasEmbed(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['embedded']);
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]);
    }

    /**
     * Checks whether this document has indexes or not.
     */
    public function hasIndexes(): bool
    {
        return $this->indexes !== [];
    }

    /**
     * Checks whether the class has callbacks registered for a lifecycle event.
     */
    public function hasLifecycleCallbacks(string $event): bool
    {
        return !empty($this->lifecycleCallbacks[$event]);
    }

    /**
     * Checks whether the class has a mapped association with the given field name.
     */
    public function hasReference(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['reference']);
    }

    /**
     * Dispatches the lifecycle event of the given document by invoking all
     * registered callbacks.
     *
     * @throws InvalidArgumentException If document class is not this class or
     *                                   a Proxy of this class.
     */
    public function invokeLifecycleCallbacks(string $event, object $document, ?array $arguments = null): void
    {
        if (!$document instanceof $this->name) {
            throw new InvalidArgumentException(
                sprintf('Expected document class "%s"; found: "%s"', $this->name, $document::class)
            );
        }

        if (empty($this->lifecycleCallbacks[$event])) {
            return;
        }

        foreach ($this->lifecycleCallbacks[$event] as $callback) {
            if ($arguments !== null) {
                $document->$callback(...$arguments);
            } else {
                $document->$callback();
            }
        }
    }

    public function isAssociationInverseSide(string $assocName): bool
    {
        throw new BadMethodCallException(__METHOD__.'() is not implemented yet.');
    }

    /**
     * Whether the change tracking policy of this class is "deferred explicit".
     */
    public function isChangeTrackingDeferredExplicit(): bool
    {
        return $this->changeTrackingPolicy === self::CHANGETRACKING_DEFERRED_EXPLICIT;
    }

    /**
     * Whether the change tracking policy of this class is "deferred implicit".
     */
    public function isChangeTrackingDeferredImplicit(): bool
    {
        return $this->changeTrackingPolicy === self::CHANGETRACKING_DEFERRED_IMPLICIT;
    }

    /**
     * Checks whether the class has a mapped reference or embed for the specified field and
     * is a collection valued association.
     */
    public function isCollectionValuedAssociation(string $fieldName): bool
    {
        return $this->isCollectionValuedReference($fieldName) || $this->isCollectionValuedEmbed($fieldName);
    }

    /**
     * Checks whether the class has a mapped embedded document for the specified field
     * and if yes, checks whether it is a collection-valued association (to-many).
     */
    public function isCollectionValuedEmbed(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['association']) &&
            $this->fieldMappings[$fieldName]['association'] === self::EMBED_MANY;
    }

    /**
     * Checks whether the class has a mapped association for the specified field
     * and if yes, checks whether it is a collection-valued association (to-many).
     */
    public function isCollectionValuedReference(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['association']) &&
            $this->fieldMappings[$fieldName]['association'] === self::REFERENCE_MANY;
    }

    public function isIdentifier(string $fieldName): bool
    {
        return $this->identifier === $fieldName;
    }

    /**
     * Checks whether a mapped field is inherited from an entity superclass.
     */
    public function isInheritedField(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['inherited']);
    }

    /**
     * Check if the field is not null.
     *
     * @throws MappingException
     */
    public function isNullable(string $fieldName): bool
    {
        $mapping = $this->getFieldMapping($fieldName);

        return isset($mapping['nullable']) && $mapping['nullable'] === true;
    }

    /**
     * Checks whether the class has a mapped reference or embed for the specified field and
     * is a single valued association.
     */
    public function isSingleValuedAssociation(string $fieldName): bool
    {
        return $this->isSingleValuedReference($fieldName) || $this->isSingleValuedEmbed($fieldName);
    }

    /**
     * Checks whether the class has a mapped embedded document for the specified field
     * and if yes, checks whether it is a single-valued association (to-one).
     */
    public function isSingleValuedEmbed(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['association']) &&
            $this->fieldMappings[$fieldName]['association'] === self::EMBED_ONE;
    }

    /**
     * Checks whether the class has a mapped association for the specified field
     * and if yes, checks whether it is a single-valued association (to-one).
     */
    public function isSingleValuedReference(string $fieldName): bool
    {
        return isset($this->fieldMappings[$fieldName]['association']) &&
            $this->fieldMappings[$fieldName]['association'] === self::REFERENCE_ONE;
    }

    /**
     * Map a field.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function mapField(array $mapping): array
    {
        if (!isset($mapping['fieldName']) && isset($mapping['name'])) {
            $mapping['fieldName'] = $mapping['name'];
        }

        if ($this->isTypedProperty($mapping['fieldName'])) {
            $mapping = $this->validateAndCompleteTypedFieldMapping($mapping);

            if (isset($mapping['type']) && $mapping['type'] === self::MANY) {
                $mapping = $this->validateAndCompleteTypedManyAssociationMapping($mapping);
            }
        }

        if (!isset($mapping['fieldName']) || !is_string($mapping['fieldName'])) {
            throw MappingException::missingFieldName($this->name);
        }

        if (!isset($mapping['name'])) {
            $mapping['name'] = $mapping['fieldName'];
        }

        if ($this->identifier === $mapping['name'] && empty($mapping['id'])) {
            throw MappingException::mustNotChangeIdentifierFieldsType($this->name, (string) $mapping['name']);
        }

        if (isset($mapping['collectionClass'])) {
            $mapping['collectionClass'] = ltrim($mapping['collectionClass'], '\\');
        }

        if (!empty($mapping['collectionClass'])) {
            $rColl = new ReflectionClass($mapping['collectionClass']);

            if (!$rColl->implementsInterface(Collection::class)) {
                throw MappingException::collectionClassDoesNotImplementCommonInterface(
                    $this->name,
                    $mapping['fieldName'],
                    $mapping['collectionClass']
                );
            }
        }

        if (isset($mapping['cascade'], $mapping['embedded'])) {
            throw MappingException::cascadeOnEmbeddedNotAllowed($this->name, $mapping['fieldName']);
        }

        $cascades = isset($mapping['cascade']) ? array_map('strtolower', (array) $mapping['cascade']) : [];

        if (isset($mapping['embedded']) || in_array('all', $cascades, true)) {
            $cascades = ['remove', 'persist', 'refresh', 'merge', 'detach'];
        }

        if (isset($mapping['embedded'])) {
            unset($mapping['cascade']);
        } else {
            if (isset($mapping['cascade'])) {
                $mapping['cascade'] = $cascades;
            }
        }

        $mapping['isCascadeRemove'] = in_array('remove', $cascades, true);
        $mapping['isCascadePersist'] = in_array('persist', $cascades, true);
        $mapping['isCascadeRefresh'] = in_array('refresh', $cascades, true);
        $mapping['isCascadeMerge'] = in_array('merge', $cascades, true);
        $mapping['isCascadeDetach'] = in_array('detach', $cascades, true);

        if (isset($mapping['id']) && $mapping['id'] === true) {
            $mapping['type'] = Type::STRING;
            $this->identifier = $mapping['fieldName'];

            unset($this->generatorOptions['type']);
        }

        if (!isset($mapping['type'])) {
            // Default to string
            $mapping['type'] = Type::STRING;
        }

        if (!isset($mapping['nullable'])) {
            $mapping['nullable'] = false;
        }

        if (isset($mapping['reference']) && !isset($mapping['targetDocument'])) {
            throw MappingException::simpleReferenceRequiresTargetDocument($this->name, $mapping['fieldName']);
        }

        if (
            isset($mapping['reference'])
            && empty($mapping['targetDocument'])
            && (isset($mapping['mappedBy']) || isset($mapping['inversedBy']))
        ) {
            throw MappingException::owningAndInverseReferencesRequireTargetDocument($this->name, $mapping['fieldName']);
        }

        if (isset($mapping['reference']) && $mapping['type'] === self::ONE) {
            $mapping['association'] = self::REFERENCE_ONE;
        }

        if (isset($mapping['reference']) && $mapping['type'] === self::MANY) {
            $mapping['association'] = self::REFERENCE_MANY;
        }

        if (isset($mapping['embedded']) && $mapping['type'] === self::ONE) {
            $mapping['association'] = self::EMBED_ONE;
        }

        if (isset($mapping['embedded']) && $mapping['type'] === self::MANY) {
            $mapping['association'] = self::EMBED_MANY;
        }

        $mapping['isOwningSide'] = true;
        $mapping['isInverseSide'] = false;
        if (isset($mapping['reference'])) {
            if (isset($mapping['inversedBy']) && $mapping['inversedBy']) {
                $mapping['isOwningSide'] = true;
                $mapping['isInverseSide'] = false;

                $this->addGlobalSecondaryIndex($mapping);
            }

            if (isset($mapping['mappedBy']) && $mapping['mappedBy']) {
                $mapping['isInverseSide'] = true;
                $mapping['isOwningSide'] = false;
            }

            if (isset($mapping['repositoryMethod'])) {
                $mapping['isInverseSide'] = true;
                $mapping['isOwningSide'] = false;
            }

            if (!isset($mapping['orphanRemoval'])) {
                $mapping['orphanRemoval'] = false;
            }
        }

        $this->checkDuplicateMapping($mapping);

        $this->fieldMappings[$mapping['fieldName']] = $mapping;
        if (isset($mapping['association'])) {
            $this->associationMappings[$mapping['fieldName']] = $mapping;
        }

        $reflProp = $this->reflectionService->getAccessibleProperty($this->name, $mapping['fieldName']);
        assert($reflProp instanceof ReflectionProperty);

        if (isset($mapping['enumType'])) {
            if (!enum_exists($mapping['enumType'])) {
                throw MappingException::nonEnumTypeMapped($this->name, $mapping['fieldName'], $mapping['enumType']);
            }

            $reflectionEnum = new ReflectionEnum($mapping['enumType']);
            if (!$reflectionEnum->isBacked()) {
                throw MappingException::nonBackedEnumMapped($this->name, $mapping['fieldName'], $mapping['enumType']);
            }

            $reflProp = new EnumReflectionProperty($reflProp, $mapping['enumType']);
        }

        $this->reflFields[$mapping['fieldName']] = $reflProp;

        return $mapping;
    }

    /**
     * Map a collection of embedded documents.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function mapManyEmbedded(array $mapping): void
    {
        $mapping['embedded'] = true;
        $mapping['type'] = self::MANY;

        $this->mapField($mapping);
    }

    /**
     * Map a collection of document references.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function mapManyReference(array $mapping): void
    {
        $mapping['reference'] = true;
        $mapping['type'] = self::MANY;

        $this->mapField($mapping);
    }

    /**
     * Map a single embedded document.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function mapOneEmbedded(array $mapping): void
    {
        $mapping['embedded'] = true;
        $mapping['type'] = self::ONE;

        $this->mapField($mapping);
    }

    /**
     * Map a single document reference.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    public function mapOneReference(array $mapping): void
    {
        $mapping['reference'] = true;
        $mapping['type'] = self::ONE;

        $this->mapField($mapping);
    }

    /**
     * Marks this class as read only, no change tracking is applied to it.
     */
    public function markReadOnly(): void
    {
        $this->isReadOnly = true;
    }

    /**
     * Creates a new instance of the mapped class, without invoking the constructor.
     *
     * @throws ExceptionInterface
     */
    public function newInstance(): object
    {
        return $this->instantiator->instantiate($this->name);
    }

    /**
     * Registers a method for loading document data before field hydration.
     *
     * Note: A method may be registered multiple times for different fields.
     * it will be invoked only once for the first field found.
     *
     * @param array<string, mixed>|string $fields Database field name(s)
     */
    public function registerAlsoLoadMethod(string $method, mixed $fields): void
    {
        $this->alsoLoadMethods[$method] = is_array($fields) ? $fields : [$fields];
    }

    /**
     * Sets the AlsoLoad methods for documents of this class.
     *
     * Any previously registered methods are overwritten.
     *
     * @param array<string, array> $methods
     */
    public function setAlsoLoadMethods(array $methods): void
    {
        $this->alsoLoadMethods = $methods;
    }

    /**
     * Sets the change tracking policy used by this class.
     */
    public function setChangeTrackingPolicy(int $policy): void
    {
        $this->changeTrackingPolicy = $policy;
    }

    /**
     * Registers a custom repository class for the document class.
     */
    public function setCustomRepositoryClass(?string $repositoryClassName): void
    {
        if ($this->isEmbeddedDocument) {
            return;
        }

        $this->customRepositoryClassName = $repositoryClassName;
    }

    /**
     * Set the database this Document is mapped to.
     */
    public function setDatabase(?string $db): void
    {
        $this->db = $db;
    }

    /**
     * Sets the specified field to the specified value on the given document.
     */
    public function setFieldValue(object $document, string $field, mixed $value): void
    {
        if ($document instanceof GhostObjectInterface && !$document->isProxyInitialized()) {
            //property changes to an uninitialized proxy will not be tracked or persisted,
            //so the proxy needs to be loaded first.
            $document->initializeProxy();
        }

        $this->reflFields[$field]->setValue($document, $value);
    }

    /**
     * Sets the ID generator used to generate IDs for instances of this class.
     */
    public function setIdGenerator(IdGenerator $generator): void
    {
        $this->idGenerator = $generator;
    }

    /**
     * Sets the Id generator options.
     *
     * @param array<string, mixed> $generatorOptions
     */
    public function setIdGeneratorOptions(array $generatorOptions): void
    {
        $this->generatorOptions = $generatorOptions;
    }

    /**
     * Sets the type of Id generator to use for the mapped class.
     */
    public function setIdGeneratorType(int $generatorType): void
    {
        $this->generatorType = $generatorType;
    }

    /**
     * Sets the document identifier of a document.
     *
     * The value will be converted to a PHP type before being set.
     */
    public function setIdentifierValue(object $document, mixed $id): void
    {
        $id = $this->getPHPIdentifierValue($id);
        $this->reflFields[$this->identifier]->setValue($document, $id);
    }

    /**
     * Sets the parent class names.
     * Assumes that the class names in the passed array are in the order:
     * directParent -> directParentParent -> directParentParentParent ... -> root.
     *
     * @param string[] $classNames
     */
    public function setParentClasses(array $classNames): void
    {
        $this->parentClasses = $classNames;

        if (count($classNames) <= 0) {
            return;
        }

        $this->rootDocumentName = (string) array_pop($classNames);
    }

    /**
     * Sets the read preference used by this class.
     *
     * @param array<array<string, string>> $tags
     */
    public function setReadPreference(?string $readPreference, array $tags): void
    {
        $this->readPreference = $readPreference;
        $this->readPreferenceTags = $tags;
    }

    /**
     * Sets the mapped subclasses of this class.
     *
     * @param string[] $subclasses The names of all mapped subclasses.
     */
    public function setSubclasses(array $subclasses): void
    {
        foreach ($subclasses as $subclass) {
            $this->subClasses[] = $subclass;
        }
    }

    private function addGlobalSecondaryIndex(array $mapping): void
    {
        $index = 1;

        do {
            $name = self::INDEX_GSI.$index;
            $added = false;

            if (!$this->getGlobalSecondaryIndex($name)) {
                $this->indexes[self::INDEX_GSI][$mapping['name']] = new Index(
                    hash: $name,
                    name: $name,
                    strategy: new IndexStrategy(hash: IndexStrategy::SK_STRATEGY_FORMAT),
                );
                $added = true;
            }

            $index++;
        } while (!$added);
    }

    /**
     * @throws MappingException
     */
    private function checkDuplicateMapping(array $mapping): void
    {
        foreach ($this->fieldMappings as $fieldName => $otherMapping) {
            // Ignore fields with the same name - we can safely override their mapping
            if ($mapping['fieldName'] === $fieldName) {
                continue;
            }

            // Ignore fields with a different name in the database
            if ($mapping['name'] !== $otherMapping['name']) {
                continue;
            }

            throw MappingException::duplicateDatabaseFieldName(
                $this->getName(),
                $mapping['fieldName'],
                $mapping['name'],
                $fieldName
            );
        }
    }

    /**
     * @throws ReflectionException
     */
    private function isTypedProperty(string $name): bool
    {
        return $this->reflClass->hasProperty($name)
            && $this->reflClass->getProperty($name)->hasType();
    }

    /**
     * Validates & completes the given field mapping based on typed property.
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    private function validateAndCompleteTypedFieldMapping(array $mapping): array
    {
        $type = $this->reflClass->getProperty($mapping['fieldName'])->getType();

        if (!$type instanceof ReflectionNamedType || isset($mapping['type'])) {
            return $mapping;
        }

        if (PHP_VERSION_ID >= 80100 && !$type->isBuiltin() && enum_exists($type->getName())) {
            $mapping['enumType'] = $type->getName();

            $reflection = new ReflectionEnum($type->getName());
            $type = $reflection->getBackingType();

            if ($type === null) {
                throw MappingException::nonBackedEnumMapped($this->name, $mapping['fieldName'], $mapping['enumType']);
            }

            assert($type instanceof ReflectionNamedType);
        }

        switch ($type->getName()) {
            case DateTime::class:
                $mapping['type'] = Type::DATE;
                break;
            case DateTimeImmutable::class:
                $mapping['type'] = Type::DATE_IMMUTABLE;
                break;
            case 'array':
                $mapping['type'] = Type::HASH;
                break;
            case 'bool':
                $mapping['type'] = Type::BOOL;
                break;
            case 'float':
                $mapping['type'] = Type::FLOAT;
                break;
            case 'int':
                $mapping['type'] = Type::INT;
                break;
            case 'string':
                $mapping['type'] = Type::STRING;
                break;
        }

        return $mapping;
    }

    /**
     * Validates & completes the basic mapping information based on typed property.
     *
     * @throws ReflectionException
     */
    private function validateAndCompleteTypedManyAssociationMapping(array $mapping): array
    {
        $type = $this->reflClass->getProperty($mapping['fieldName'])->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $mapping;
        }

        if (!isset($mapping['collectionClass']) && class_exists($type->getName())) {
            $mapping['collectionClass'] = $type->getName();
        }

        return $mapping;
    }
}

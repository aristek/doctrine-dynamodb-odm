<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Persisters;

use InvalidArgumentException;
use ReflectionException;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Types\Type;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use UnexpectedValueException;
use function array_merge;

/**
 * PersistenceBuilder builds the queries used by the persisters to update and insert
 * documents when a DocumentManager is flushed. It uses the changeset information in the
 * UnitOfWork to build queries using atomic operators like $set, $unset, etc.
 *
 * @internal
 */
final class PersistenceBuilder
{
    private DocumentManager $dm;

    private UnitOfWork $uow;

    public function __construct(DocumentManager $dm, UnitOfWork $uow)
    {
        $this->dm = $dm;
        $this->uow = $uow;
    }

    /**
     * Returns the collection representation to be stored and unschedules it afterwards.
     */
    public function prepareAssociatedCollectionValue(
        PersistentCollectionInterface $coll,
        bool $includeNestedCollections = false
    ): array {
        $mapping = $coll->getMapping();
        $pb = $this;
        $callback = isset($mapping['embedded'])
            ? static fn($v) => $pb->prepareEmbeddedDocumentValue($v, $includeNestedCollections)
            : static fn($v) => $pb->prepareReferencedDocumentValue($mapping, $v);

        $setData = $coll->map($callback)->toArray();

        $this->uow->unscheduleCollectionDeletion($coll);
        $this->uow->unscheduleCollectionUpdate($coll);

        return $setData;
    }

    /**
     * Returns the embedded document or reference representation to be stored.
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function prepareAssociatedDocumentValue(
        array $mapping,
        object $document,
        bool $includeNestedCollections = false
    ): array|object|null|string {
        if (isset($mapping['embedded'])) {
            return $this->prepareEmbeddedDocumentValue($document, $includeNestedCollections);
        }

        if (isset($mapping['reference'])) {
            return $this->prepareReferencedDocumentValue($mapping, $document);
        }

        throw new InvalidArgumentException('Mapping is neither embedded nor reference.');
    }

    /**
     * Returns the embedded document to be stored in MongoDB.
     *
     * The return value will usually be an associative array with string keys
     * corresponding to field names on the embedded document. An object may be
     * returned if the document is empty, to ensure that a BSON object will be
     * stored in lieu of an array.
     *
     * If $includeNestedCollections is true, nested collections will be included
     * in this prepared value and the option will cascade to all embedded
     * associations. If any nested PersistentCollections (embed or reference)
     * within this value were previously scheduled for deletion or update, they
     * will also be unscheduled.
     *
     * @return array<string, mixed>|object
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function prepareEmbeddedDocumentValue(
        object $embeddedDocument,
        bool $includeNestedCollections = false
    ): array|object {
        $embeddedDocumentValue = [];
        $class = $this->dm->getClassMetadata($embeddedDocument::class);

        foreach ($class->fieldMappings as $mapping) {
            // Inline ClassMetadata::getFieldValue()
            $rawValue = $class->reflFields[$mapping['fieldName']]->getValue($embeddedDocument);

            $value = null;

            if ($rawValue !== null) {
                switch ($mapping['association'] ?? null) {
                    // @Field, @String, @Date, etc.
                    case null:
                        $value = Type::getType($mapping['type'])->convertToDatabaseValue($rawValue);
                        break;

                    case ClassMetadata::EMBED_ONE:
                    case ClassMetadata::REFERENCE_ONE:
                        // Nested collections should only be included for embedded relationships
                        $value = $this->prepareAssociatedDocumentValue(
                            $mapping,
                            $rawValue,
                            $includeNestedCollections && isset($mapping['embedded'])
                        );
                        break;

                    case ClassMetadata::EMBED_MANY:
                    case ClassMetadata::REFERENCE_MANY:
                        // Skip PersistentCollections already scheduled for deletion
                        if (
                            !$includeNestedCollections && $rawValue instanceof PersistentCollectionInterface
                            && $this->uow->isCollectionScheduledForDeletion($rawValue)
                        ) {
                            break;
                        }

                        $value = $this->prepareAssociatedCollectionValue($rawValue, $includeNestedCollections);
                        break;

                    default:
                        throw new UnexpectedValueException('Unsupported mapping association: '.$mapping['association']);
                }
            }

            // Omit non-nullable fields that would have a null value
            if ($value === null && $mapping['nullable'] === false) {
                continue;
            }

            $embeddedDocumentValue[$mapping['name']] = $value;
        }

        // Ensure empty embedded documents are stored as BSON objects
        if (empty($embeddedDocumentValue)) {
            return (object) $embeddedDocumentValue;
        }

        /* @todo Consider always casting the return value to an object, or
         * building $embeddedDocumentValue as an object instead of an array, to
         * handle the edge case where all database field names are sequential,
         * numeric keys.
         */
        return $embeddedDocumentValue;
    }

    /**
     * Prepares the array that is ready to be inserted to mongodb for a given object document.
     *
     * @return array<string, mixed> $insertData
     *
     * @throws MappingException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws ReflectionException
     */
    public function prepareInsertData(object $document): array
    {
        $class = $this->dm->getClassMetadata($document::class);
        $changeset = $this->uow->getDocumentChangeSet($document);

        $insertData = [];
        foreach ($class->fieldMappings as $mapping) {
            $new = $changeset[$mapping['fieldName']][1] ?? null;

            if ($new === null) {
                if ($mapping['nullable']) {
                    $insertData[$mapping['name']] = null;
                }

                continue;
            }

            // @Field, @String, @Date, etc.
            if (!isset($mapping['association'])) {
                $insertData[$mapping['name']] = Type::getType($mapping['type'])->convertToDatabaseValue($new);
                // @ReferenceOne
            } else {
                if ($mapping['association'] === ClassMetadata::REFERENCE_ONE) {
                    $insertData[$mapping['name']] = $this->prepareReferencedDocumentValue($mapping, $new);
                    // @EmbedOne
                } else {
                    if ($mapping['association'] === ClassMetadata::EMBED_ONE) {
                        $insertData[$mapping['name']] = $this->prepareEmbeddedDocumentValue($new);

                        // @ReferenceMany, @EmbedMany
                        // We're excluding collections using addToSet since there is a risk
                        // of duplicated entries stored in the collection
                    } else {
                        if (
                            $mapping['type'] === ClassMetadata::MANY && !$mapping['isInverseSide']
                            && !$new->isEmpty()
                        ) {
                            $insertData[$mapping['name']] = $this->prepareAssociatedCollectionValue($new, true);
                        }
                    }
                }
            }
        }

        return $this->addIndexesData($class, $document, $insertData);
    }

    /**
     * Returns the reference representation to be stored in MongoDB.
     *
     * If the document does not have an identifier and the mapping calls for a
     * simple reference, null may be returned.
     *
     * @param array  $referenceMapping
     * @param object $document
     *
     * @return array<string, mixed>|null
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function prepareReferencedDocumentValue(array $referenceMapping, object $document): mixed
    {
        return $this->dm->createReference($document, $referenceMapping);
    }

    /**
     * Prepares the update query to update a given document object in mongodb.
     *
     * @return array<string, mixed> $updateData
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function prepareUpdateData(object $document): array
    {
        $class = $this->dm->getClassMetadata($document::class);
        $changeset = $this->uow->getDocumentChangeSet($document);

        $updateData = [];
        foreach ($changeset as $fieldName => $change) {
            $mapping = $class->fieldMappings[$fieldName];

            // skip non embedded document identifiers
            if (!$class->isEmbeddedDocument && !empty($mapping['id'])) {
                continue;
            }

            [$old, $new] = $change;

            if ($new === null && $mapping['nullable'] === true) {
                $updateData[$mapping['name']] = null;

                continue;
            }

            // Scalar fields
            if (!isset($mapping['association'])) {
                $value = Type::getType($mapping['type'])->convertToDatabaseValue($new);
                $updateData[$mapping['name']] = $value;

                continue;
            }

            // @EmbedOne
            if ($mapping['association'] === ClassMetadata::EMBED_ONE) {
                // If we have a new embedded document then lets set the whole thing
                if ($this->uow->isScheduledForInsert($new)) {
                    $updateData[$mapping['name']] = $this->prepareEmbeddedDocumentValue($new);
                    // Update existing embedded document
                } else {
                    $update = $this->prepareUpdateData($new);
                    foreach ($update as $key => $values) {
                        $updateData[$mapping['name'].'.'.$key] = $values;
                    }
                }

                continue;
            }

            // @ReferenceMany, @EmbedMany
            if ($mapping['type'] === ClassMetadata::MANY && $mapping['association'] === ClassMetadata::EMBED_MANY) {
                foreach ($new as $key => $embeddedDoc) {
                    if ($this->uow->isScheduledForInsert($embeddedDoc)) {
                        continue;
                    }

                    $update = $this->prepareUpdateData($embeddedDoc);
                    foreach ($update as $prop => $values) {
                        $updateData[$mapping['name'].'.'.$key.'.'.$prop] = $values;
                    }
                }

                continue;
            }

            // @ReferenceOne
            if ($mapping['association'] === ClassMetadata::REFERENCE_ONE
                && (!isset($mapping['inversedBy']) || !$mapping['inversedBy'])
            ) {
                $updateData[$mapping['name']] = $this->prepareReferencedDocumentValue($mapping, $new);
            }
        }

        return $updateData;
    }

    /**
     * Prepares the update query to upsert a given document object in mongodb.
     *
     * @return array<string, mixed> $updateData
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function prepareUpsertData(object $document): array
    {
        $class = $this->dm->getClassMetadata($document::class);
        $changeset = $this->uow->getDocumentChangeSet($document);

        $updateData = [];
        foreach ($changeset as $fieldName => $change) {
            $mapping = $class->fieldMappings[$fieldName];

            [$old, $new] = $change;

            // Fields with a null value should only be written for inserts
            if ($new === null) {
                if ($mapping['nullable'] === true) {
                    $updateData[$mapping['name']] = null;
                }

                continue;
            }

            // Scalar fields
            if (!isset($mapping['association'])) {
                $value = Type::getType($mapping['type'])->convertToDatabaseValue($new);

                $updateData[$mapping['name']] = $value;
                // @EmbedOne
            } else {
                if ($mapping['association'] === ClassMetadata::EMBED_ONE) {
                    // If we don't have a new value then do nothing on upsert
                    // If we have a new embedded document then lets set the whole thing
                    if ($this->uow->isScheduledForInsert($new)) {
                        $updateData[$mapping['name']] = $this->prepareEmbeddedDocumentValue($new);
                    } else {
                        // Update existing embedded document
                        $update = $this->prepareUpsertData($new);
                        foreach ($update as $cmd => $values) {
                            foreach ($values as $key => $value) {
                                $updateData[$cmd][$mapping['name'].'.'.$key] = $value;
                            }
                        }
                    }
                    // @ReferenceOne
                } else {
                    if ($mapping['association'] === ClassMetadata::REFERENCE_ONE) {
                        if (!$class->getGlobalSecondaryIndex($mapping['name'])) {
                            $updateData[$mapping['name']] = $this->prepareReferencedDocumentValue($mapping, $new);
                        }
                        // @ReferenceMany, @EmbedMany
                    } else {
                        if (
                            $mapping['type'] === ClassMetadata::MANY && !$mapping['isInverseSide']
                            && $new instanceof PersistentCollectionInterface && $new->isDirty()
                        ) {
                            $updateData[$mapping['name']] = $this->prepareAssociatedCollectionValue($new, true);
                        }
                    }
                }
            }
            // @EmbedMany and @ReferenceMany are handled by CollectionPersister
        }

        return $this->addIndexesData($class, $document, $updateData);
    }

    private function addIndexesData(ClassMetadata $class, object $document, array $insertData): array
    {
        return array_merge($insertData, $class->getIndexesData($document));
    }
}

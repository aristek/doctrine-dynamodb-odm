<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Driver;

use Aristek\Bundle\DynamodbBundle\ODM\Events;
use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations as ODM;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Index;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\Mapping\Driver\ColocatedMappingDriver;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use function array_replace;
use function assert;
use function interface_exists;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 */
class AnnotationDriver extends CompatibilityAnnotationDriver
{
    use ColocatedMappingDriver;

    /**
     * The annotation reader.
     *
     * @internal this property will be private in 3.0
     */
    protected Reader $reader;

    /**
     * Factory method for the Annotation Driver
     *
     * @param string[]|string $paths
     */
    public static function create(array|string $paths = [], ?Reader $reader = null): AnnotationDriver
    {
        return new self($reader ?? new AnnotationReader(), $paths);
    }

    /**
     * Initializes a new AnnotationDriver that uses the given AnnotationReader for reading docblock annotations.
     *
     * @param string|string[]|null $paths One or multiple paths where mapping classes can be found.
     */
    public function __construct(Reader $reader, array|string $paths = null)
    {
        $this->reader = $reader;

        $this->addPaths((array) $paths);
    }

    /**
     * @throws ReflectionException
     */
    public function isTransient(object|string $className): bool
    {
        $classAnnotations = $this->reader->getClassAnnotations(new ReflectionClass($className));

        foreach ($classAnnotations as $annot) {
            if ($annot instanceof ODM\AbstractDocument) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws MappingException
     * @throws ReflectionException
     */
    public function loadMetadataForClass($className, \Doctrine\Persistence\Mapping\ClassMetadata $metadata): void
    {
        assert($metadata instanceof ClassMetadata);
        $reflClass = $metadata->getReflectionClass();

        $classAnnotations = $this->reader->getClassAnnotations($reflClass);

        $documentAnnot = null;
        foreach ($classAnnotations as $annot) {
            $classAnnotations[$annot::class] = $annot;

            if ($annot instanceof ODM\AbstractDocument) {
                if ($documentAnnot !== null) {
                    throw MappingException::classCanOnlyBeMappedByOneAbstractDocument(
                        $className,
                        $documentAnnot,
                        $annot
                    );
                }

                $documentAnnot = $annot;
            }
        }

        if ($documentAnnot === null) {
            throw MappingException::classIsNotAValidDocument($className);
        }

        if ($documentAnnot instanceof ODM\MappedSuperclass) {
            $metadata->isMappedSuperclass = true;
        } else {
            if ($documentAnnot instanceof ODM\EmbeddedDocument) {
                $metadata->isEmbeddedDocument = true;
            }
        }

        if (isset($documentAnnot->db)) {
            $metadata->setDatabase($documentAnnot->db);
        }

        if (isset($documentAnnot->repositoryClass)) {
            $metadata->setCustomRepositoryClass($documentAnnot->repositoryClass);
        }

        if (!empty($documentAnnot->readOnly)) {
            $metadata->markReadOnly();
        }

        foreach ($reflClass->getProperties() as $property) {
            if (($metadata->isMappedSuperclass && !$property->isPrivate())
                || ($metadata->isInheritedField($property->name)
                    && $property->getDeclaringClass()->name !== $metadata->name
                )
            ) {
                continue;
            }

            $mapping = ['fieldName' => $property->getName()];
            $fieldAnnot = null;

            foreach ($this->reader->getPropertyAnnotations($property) as $annot) {
                if ($annot instanceof ODM\AbstractField) {
                    $fieldAnnot = $annot;
                }

                if ($annot instanceof ODM\AlsoLoad) {
                    $mapping['alsoLoadFields'] = (array) $annot->value;
                }
            }

            if ($fieldAnnot) {
                $mapping = array_replace($mapping, (array) $fieldAnnot);
                $metadata->mapField($mapping);
            }
        }

        if ($documentAnnot instanceof ODM\Document) {
            if (!isset($metadata->identifier[PrimaryKey::RANGE])) {
                $metadata->identifier[PrimaryKey::RANGE] = [
                    $metadata::ID_KEY      => PrimaryKey::RANGE,
                    $metadata::ID_FIELD    => null,
                    $metadata::ID_STRATEGY => null,
                ];
            }

            if (count($metadata->getIdentifier()) !== 2) {
                throw new LogicException('Attributes Pk and Sk are required.');
            }

            $primaryIndexKeys = $metadata->getIdentifierKeys();
            $primaryIndexFields = $metadata->getIdentifierFields();
            $primaryIndexStrategies = $metadata->getIdentifierStrategies();
            $metadata->addIndex(
                new Index(
                    hashKey: new ODM\HashKey(
                        key: $primaryIndexKeys[PrimaryKey::HASH],
                        field: $primaryIndexFields[PrimaryKey::HASH],
                        strategy: $primaryIndexStrategies[PrimaryKey::HASH] ?? $documentAnnot->indexStrategy->hash->mask
                    ),
                    name: '',
                    rangeKey: new ODM\RangeKey(
                        key: $primaryIndexKeys[PrimaryKey::RANGE],
                        field: $primaryIndexFields[PrimaryKey::RANGE],
                        strategy: $primaryIndexStrategies[PrimaryKey::RANGE] ?? $documentAnnot->indexStrategy->range->mask
                    )
                )
            );

            foreach ($documentAnnot->globalSecondaryIndexes as $globalSecondaryIndex) {
                $metadata->addIndex($globalSecondaryIndex, ClassMetadata::INDEX_GSI);
            }

            foreach ($documentAnnot->localSecondaryIndexes as $localSecondaryIndices) {
                $metadata->addIndex($localSecondaryIndices, ClassMetadata::INDEX_LSI);
            }
        }

        foreach ($reflClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /* Filter for the declaring class only. Callbacks from parent
             * classes will already be registered.
             */
            if ($method->getDeclaringClass()->name !== $reflClass->name) {
                continue;
            }

            foreach ($this->reader->getMethodAnnotations($method) as $annot) {
                if ($annot instanceof ODM\AlsoLoad) {
                    $metadata->registerAlsoLoadMethod($method->getName(), $annot->value);
                }

                if (!isset($classAnnotations[ODM\HasLifecycleCallbacks::class])) {
                    continue;
                }

                if ($annot instanceof ODM\PrePersist) {
                    $metadata->addLifecycleCallback($method->getName(), Events::prePersist);
                } elseif ($annot instanceof ODM\PostPersist) {
                    $metadata->addLifecycleCallback($method->getName(), Events::postPersist);
                } elseif ($annot instanceof ODM\PreUpdate) {
                    $metadata->addLifecycleCallback($method->getName(), Events::preUpdate);
                } elseif ($annot instanceof ODM\PostUpdate) {
                    $metadata->addLifecycleCallback($method->getName(), Events::postUpdate);
                } elseif ($annot instanceof ODM\PreRemove) {
                    $metadata->addLifecycleCallback($method->getName(), Events::preRemove);
                } elseif ($annot instanceof ODM\PostRemove) {
                    $metadata->addLifecycleCallback($method->getName(), Events::postRemove);
                } elseif ($annot instanceof ODM\PreLoad) {
                    $metadata->addLifecycleCallback($method->getName(), Events::preLoad);
                } elseif ($annot instanceof ODM\PostLoad) {
                    $metadata->addLifecycleCallback($method->getName(), Events::postLoad);
                } elseif ($annot instanceof ODM\PreFlush) {
                    $metadata->addLifecycleCallback(
                        $method->getName(),
                        Events::preFlush
                    );
                }
            }
        }
    }
}

interface_exists(\Doctrine\Persistence\Mapping\ClassMetadata::class);

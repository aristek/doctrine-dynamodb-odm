<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping;

use Aristek\Bundle\DynamodbBundle\ODM\Configuration;
use Aristek\Bundle\DynamodbBundle\ODM\ConfigurationException;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Event\LoadClassMetadataEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Event\OnClassMetadataNotFoundEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Events;
use Aristek\Bundle\DynamodbBundle\ODM\Id\UuidGenerator;
use Doctrine\Common\EventManager;
use Doctrine\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\ReflectionService;
use ReflectionException;
use function assert;
use function interface_exists;

/**
 * The ClassMetadataFactory is used to create ClassMetadata objects that contain all the
 * metadata mapping information of a class which describes how a class should be mapped to a document database.
 *
 * @internal
 *
 * @method list<ClassMetadata> getAllMetadata()
 * @method ClassMetadata[] getLoadedMetadata()
 * @method ClassMetadata getMetadataFor($className)
 */
final class ClassMetadataFactory extends AbstractClassMetadataFactory
{
    /**
     * @var string
     */
    protected $cacheSalt = '$DYNAMODBODMCLASSMETADATA';

    private Configuration $config;

    private DocumentManager $dm;

    private MappingDriver $driver;

    private EventManager $evm;

    public function setConfiguration(Configuration $config): void
    {
        $this->config = $config;
    }

    public function setDocumentManager(DocumentManager $dm): void
    {
        $this->dm = $dm;
    }

    /**
     * @throws MappingException
     */
    protected function doLoadMetadata($class, $parent, bool $rootEntityFound, array $nonSuperclassParents = []): void
    {
        assert($class instanceof ClassMetadata);

        if ($parent instanceof ClassMetadata) {
            $class->setIdGeneratorType($parent->generatorType);
            $this->addInheritedFields($class, $parent);
            $this->addInheritedRelations($class, $parent);
            $this->addInheritedIndexes($class, $parent);
            $class->setLifecycleCallbacks($parent->lifecycleCallbacks);
            $class->setAlsoLoadMethods($parent->alsoLoadMethods);
            $class->setChangeTrackingPolicy($parent->changeTrackingPolicy);
            $class->setReadPreference($parent->readPreference, $parent->readPreferenceTags);

            if ($parent->isMappedSuperclass) {
                $class->setCustomRepositoryClass($parent->customRepositoryClassName);
            }
        }

        // Invoke driver
        try {
            $this->driver->loadMetadataForClass($class->getName(), $class);
        } catch (ReflectionException $e) {
            throw MappingException::reflectionFailure($class->getName(), $e);
        }

        $this->validateIdentifier($class);

        if ($parent instanceof ClassMetadata && $rootEntityFound && $parent->generatorType === $class->generatorType) {
            if ($parent->generatorType) {
                $class->setIdGeneratorType($parent->generatorType);
            }

            if ($parent->generatorOptions) {
                $class->setIdGeneratorOptions($parent->generatorOptions);
            }

            if ($parent->idGenerator) {
                $class->setIdGenerator($parent->idGenerator);
            }
        } else {
            $this->completeIdGeneratorMapping($class);
        }

        $class->setParentClasses($nonSuperclassParents);

        $this->evm->dispatchEvent(
            Events::loadClassMetadata,
            new LoadClassMetadataEventArgs($class, $this->dm),
        );
    }

    protected function getDriver(): MappingDriver
    {
        return $this->driver;
    }

    /**
     * Lazy initialization of this stuff, especially the metadata driver,
     * since these are not needed at all when a metadata cache is active.
     *
     * @throws ConfigurationException
     */
    protected function initialize(): void
    {
        $driver = $this->config->getMetadataDriverImpl();
        if ($driver === null) {
            throw ConfigurationException::noMetadataDriverConfigured();
        }

        $this->driver = $driver;
        $this->evm = $this->dm->getEventManager();
        $this->initialized = true;
    }

    protected function initializeReflection(ClassMetadataInterface $class, ReflectionService $reflService): void
    {
    }

    protected function isEntity(ClassMetadataInterface $class): bool
    {
        assert($class instanceof ClassMetadata);

        return !$class->isMappedSuperclass && !$class->isEmbeddedDocument;
    }

    /**
     * @throws ReflectionException
     */
    protected function newClassMetadataInstance($className): ClassMetadata
    {
        return new ClassMetadata($className);
    }

    protected function onNotFoundMetadata(string $className): ClassMetadata|ClassMetadataInterface|null
    {
        if (!$this->evm->hasListeners(Events::onClassMetadataNotFound)) {
            return null;
        }

        $eventArgs = new OnClassMetadataNotFoundEventArgs($className, $this->dm);

        $this->evm->dispatchEvent(Events::onClassMetadataNotFound, $eventArgs);

        return $eventArgs->getFoundMetadata();
    }

    /**
     * Validates the identifier mapping.
     *
     * @throws MappingException
     */
    protected function validateIdentifier(ClassMetadata $class): void
    {
        if (!$class->getPrimaryIndex() && $this->isEntity($class)) {
            throw MappingException::identifierRequired($class->name);
        }
    }

    protected function wakeupReflection(ClassMetadataInterface $class, ReflectionService $reflService): void
    {
    }

    /**
     * Adds inherited fields to the subclass mapping.
     */
    private function addInheritedFields(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->fieldMappings as $mapping) {
            if (!isset($mapping['inherited']) && !$parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }

            if (!isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }

            $subClass->addInheritedFieldMapping($mapping);
        }

        foreach ($parentClass->reflFields as $name => $field) {
            $subClass->reflFields[$name] = $field;
        }
    }

    /**
     * Adds inherited indexes to the subclass mapping.
     */
    private function addInheritedIndexes(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->indexes as $index) {
            $subClass->addIndex($index['keys'], $index['options']);
        }
    }

    /**
     * Adds inherited association mappings to the subclass mapping.
     */
    private function addInheritedRelations(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->associationMappings as $mapping) {
            if ($parentClass->isMappedSuperclass) {
                $mapping['sourceDocument'] = $subClass->name;
            }

            if (!isset($mapping['inherited']) && !$parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }

            if (!isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }

            $subClass->addInheritedAssociationMapping($mapping);
        }
    }

    private function completeIdGeneratorMapping(ClassMetadata $class): void
    {
        $class->setIdGenerator(new UuidGenerator());
    }
}

interface_exists(ClassMetadataInterface::class);
interface_exists(ReflectionService::class);

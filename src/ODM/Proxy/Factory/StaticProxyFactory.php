<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Proxy\Factory;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentNotFoundException;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Persisters\DocumentPersister;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use Aristek\Bundle\DynamodbBundle\ODM\Utility\LifecycleEventManager;
use Closure;
use Doctrine\Persistence\NotifyPropertyChanged;
use ProxyManager\Factory\LazyLoadingGhostFactory;
use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionProperty;
use Throwable;
use function array_filter;
use function count;

/**
 * This factory is used to create proxy objects for documents at runtime.
 */
final class StaticProxyFactory implements ProxyFactory
{
    private LifecycleEventManager $lifecycleEventManager;

    private LazyLoadingGhostFactory $proxyFactory;

    private UnitOfWork $uow;

    public function __construct(DocumentManager $documentManager)
    {
        $this->uow = $documentManager->getUnitOfWork();
        $this->lifecycleEventManager = new LifecycleEventManager(
            $documentManager,
            $this->uow,
            $documentManager->getEventManager()
        );
        $this->proxyFactory = $documentManager->getConfiguration()->buildGhostObjectFactory();
    }

    public function generateProxyClasses(array $classes): int
    {
        $concreteClasses = array_filter(
            $classes,
            static fn(ClassMetadata $metadata): bool => !(
                $metadata->isMappedSuperclass
                || $metadata->getReflectionClass()->isAbstract()
            )
        );

        foreach ($concreteClasses as $metadata) {
            $this
                ->proxyFactory
                ->createProxy(
                    $metadata->getName(),
                    static fn(): bool => true, // empty closure, serves its purpose, for now
                    [
                        'skippedProperties' => $this->skippedFieldsFqns($metadata),
                    ],
                );
        }

        return count($concreteClasses);
    }

    public function getProxy(ClassMetadata $metadata, mixed $identifier): GhostObjectInterface
    {
        $documentPersister = $this->uow->getDocumentPersister($metadata->getName());

        $ghostObject = $this
            ->proxyFactory
            ->createProxy(
                $metadata->getName(),
                $this->createInitializer($metadata, $documentPersister),
                [
                    'skippedProperties' => $this->skippedFieldsFqns($metadata),
                ],
            );

        $metadata->setIdentifierValue($ghostObject, $identifier);

        return $ghostObject;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param DocumentPersister<object> $documentPersister
     *
     * @return Closure
     */
    private function createInitializer(
        ClassMetadata $metadata,
        DocumentPersister $documentPersister,
    ): Closure {
        return function (
            GhostObjectInterface $ghostObject,
            string $method, // we don't care
            array $parameters, // we don't care
            &$initializer,
            array $properties // we currently do not use this
        ) use (
            $metadata,
            $documentPersister,
        ): bool {
            $originalInitializer = $initializer;
            $initializer = null;

            $id = $metadata->getIdentifierValue($ghostObject);

            [$pk, $sk] = $metadata->getIdentifierFieldNames();
            $attributes[$pk] = $id[0];
            if (!empty($id[1])) {
                $attributes[$sk] = $id[1];
            }

            $identifier = $metadata->getPrimaryIndexData($metadata->getName(), $attributes);

            try {
                $document = $documentPersister->load($identifier, $ghostObject);
            } catch (Throwable $exception) {
                $initializer = $originalInitializer;

                throw $exception;
            }

            if (!$document) {
                $initializer = $originalInitializer;

                if (!$this->lifecycleEventManager->documentNotFound($ghostObject, $identifier)) {
                    throw DocumentNotFoundException::documentNotFound($metadata->getName(), $identifier);
                }
            }

            if ($ghostObject instanceof NotifyPropertyChanged) {
                $ghostObject->addPropertyChangedListener($this->uow);
            }

            return true;
        };
    }

    private function propertyFqcn(ReflectionProperty $property): string
    {
        if ($property->isPrivate()) {
            return "\0".$property->getDeclaringClass()->getName()."\0".$property->getName();
        }

        if ($property->isProtected()) {
            return "\0*\0".$property->getName();
        }

        return $property->getName();
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<int, string>
     */
    private function skippedFieldsFqns(ClassMetadata $metadata): array
    {
        $idFieldFqcns = [];

        foreach ($metadata->getIdentifierFieldNames() as $idField) {
            if (!$idField) {
                continue;
            }

            $idFieldFqcns[] = $this->propertyFqcn($metadata->getReflectionProperty($idField));
        }

        return $idFieldFqcns;
    }
}

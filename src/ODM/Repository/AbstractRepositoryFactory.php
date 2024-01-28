<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use ReflectionException;
use function is_a;
use function spl_object_hash;

/**
 * Abstract factory for creating document repositories.
 */
abstract class AbstractRepositoryFactory implements RepositoryFactory
{
    /**
     * The list of DocumentRepository instances.
     *
     * @var ObjectRepositoryInterface<object>[]
     */
    private array $repositoryList = [];

    /**
     * @throws MappingException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws ReflectionException
     */
    public function getRepository(DocumentManager $documentManager, string $documentName): ObjectRepositoryInterface
    {
        $metadata = $documentManager->getClassMetadata($documentName);
        $hashKey = $metadata->getName().spl_object_hash($documentManager);

        if (isset($this->repositoryList[$hashKey])) {
            return $this->repositoryList[$hashKey];
        }

        $repository = $this->createRepository($documentManager, $documentName);

        $this->repositoryList[$hashKey] = $repository;

        return $repository;
    }

    /**
     * Create a new repository instance for a document class.
     *
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    protected function createRepository(DocumentManager $documentManager, string $documentName): ObjectRepositoryInterface
    {
        $metadata = $documentManager->getClassMetadata($documentName);

        $repositoryClassName = $documentManager->getConfiguration()->getDefaultDocumentRepositoryClassName();

        if ($metadata->customRepositoryClassName) {
            $repositoryClassName = $metadata->customRepositoryClassName;
        }

        if (!is_a($repositoryClassName, DocumentRepository::class, true)) {
            throw MappingException::invalidRepositoryClass(
                $documentName,
                $repositoryClassName,
                DocumentRepository::class
            );
        }

        return $this->instantiateRepository($repositoryClassName, $documentManager, $metadata);
    }

    /**
     * Instantiates requested repository.
     */
    abstract protected function instantiateRepository(
        string $repositoryClassName,
        DocumentManager $documentManager,
        ClassMetadata $metadata
    ): ObjectRepositoryInterface;
}

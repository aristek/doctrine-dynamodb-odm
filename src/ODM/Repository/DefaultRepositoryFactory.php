<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectRepository;

/**
 * This factory is used to create default repository objects for documents at runtime.
 */
final class DefaultRepositoryFactory extends AbstractRepositoryFactory
{
    protected function instantiateRepository(
        string $repositoryClassName,
        DocumentManager $documentManager,
        ClassMetadata $metadata
    ): ObjectRepository {
        return new $repositoryClassName($documentManager, $documentManager->getUnitOfWork(), $metadata);
    }
}

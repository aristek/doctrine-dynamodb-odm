<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Doctrine\Persistence\ObjectRepository;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

/**
 * Interface for document repository factory.
 */
interface RepositoryFactory
{
    /**
     * Gets the repository for a document class.
     */
    public function getRepository(DocumentManager $documentManager, string $documentName): ObjectRepository;
}

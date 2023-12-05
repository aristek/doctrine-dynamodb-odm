<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection;

use Doctrine\Common\Collections\Collection as BaseCollection;

/**
 * Default factory class for persistent collection classes.
 */
final class DefaultPersistentCollectionFactory extends AbstractPersistentCollectionFactory
{
    protected function createCollectionClass(string $collectionClass): BaseCollection
    {
        return new $collectionClass();
    }
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection;

use Doctrine\Common\Collections\Collection as BaseCollection;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

/**
 * Interface for persistent collection classes factory.
 */
interface PersistentCollectionFactory
{
    /**
     * Creates specified persistent collection to work with given collection class.
     */
    public function create(
        DocumentManager $dm,
        array $mapping,
        ?BaseCollection $coll = null
    ): PersistentCollectionInterface;
}

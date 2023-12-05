<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionInterface;

/**
 * Class that holds arguments for postCollectionLoad event.
 */
final class PostCollectionLoadEventArgs extends ManagerEventArgs
{
    public function __construct(
        private readonly PersistentCollectionInterface $collection,
        DocumentManager $dm,
    ) {
        parent::__construct($dm);
    }

    /**
     * Gets collection that was just initialized (loaded).
     */
    public function getCollection(): PersistentCollectionInterface
    {
        return $this->collection;
    }
}

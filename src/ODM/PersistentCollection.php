<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM;

use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionInterface;
use Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection\PersistentCollectionTrait;
use Doctrine\Common\Collections\Collection as BaseCollection;

/**
 * A PersistentCollection represents a collection of elements that have persistent state.
 */
final class PersistentCollection implements PersistentCollectionInterface
{
    use PersistentCollectionTrait;

    public function __construct(BaseCollection $coll, DocumentManager $dm, UnitOfWork $uow)
    {
        $this->coll = $coll;
        $this->dm = $dm;
        $this->uow = $uow;
    }
}

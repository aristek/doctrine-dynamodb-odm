<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Doctrine\Persistence\Event\ManagerEventArgs as BaseManagerEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

/**
 * Provides event arguments for the flush events.
 */
class ManagerEventArgs extends BaseManagerEventArgs
{
    public function getDocumentManager(): DocumentManager
    {
        return $this->getObjectManager();
    }
}

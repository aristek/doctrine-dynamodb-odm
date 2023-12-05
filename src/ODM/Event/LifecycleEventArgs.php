<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Doctrine\Persistence\Event\LifecycleEventArgs as BaseLifecycleEventArgs;

/**
 * Lifecycle Events are triggered by the UnitOfWork during lifecycle transitions of documents.
 */
class LifecycleEventArgs extends BaseLifecycleEventArgs
{
    public function getDocument(): object
    {
        return $this->getObject();
    }

    public function getDocumentManager(): DocumentManager
    {
        return $this->getObjectManager();
    }
}

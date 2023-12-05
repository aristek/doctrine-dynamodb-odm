<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Doctrine\Persistence\Event\OnClearEventArgs as BaseOnClearEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

/**
 * Provides event arguments for the onClear event.
 */
final class OnClearEventArgs extends BaseOnClearEventArgs
{
    public function __construct($objectManager)
    {
        parent::__construct($objectManager);
    }

    public function getDocumentManager(): DocumentManager
    {
        return $this->getObjectManager();
    }
}

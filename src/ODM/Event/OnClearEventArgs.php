<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Doctrine\Persistence\Event\OnClearEventArgs as BaseOnClearEventArgs;

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

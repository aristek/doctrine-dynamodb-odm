<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs as BaseLoadClassMetadataEventArgs;

/**
 * Class that holds event arguments for a loadMetadata event.
 */
final class LoadClassMetadataEventArgs extends BaseLoadClassMetadataEventArgs
{
    public function getDocumentManager(): DocumentManager
    {
        return $this->getObjectManager();
    }
}

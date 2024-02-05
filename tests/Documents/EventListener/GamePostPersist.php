<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\EventListener;

use Aristek\Bundle\DynamodbBundle\ODM\Event\LifecycleEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Events;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\AsDocumentListener;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\Game;

#[AsDocumentListener(Events::postPersist)]
final class GamePostPersist
{
    public function postPersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getDocument();
        if (!$entity instanceof Game) {
            return;
        }
    }
}

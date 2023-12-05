<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;

/**
 * Class that holds event arguments for a `onClassMetadataNotFound` event.
 *
 * This object is mutable by design, allowing callbacks having access to it to set the
 * found metadata in it, and therefore "cancelling" a `onClassMetadataNotFound` event
 */
final class OnClassMetadataNotFoundEventArgs extends ManagerEventArgs
{
    private ?ClassMetadata $foundMetadata = null;

    public function __construct(
        private readonly string $className,
        DocumentManager $dm
    ) {
        parent::__construct($dm);
    }

    /**
     * Retrieve class name for which a failed metadata fetch attempt was executed
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function getFoundMetadata(): ?ClassMetadata
    {
        return $this->foundMetadata;
    }

    public function setFoundMetadata(?ClassMetadata $classMetadata = null): void
    {
        $this->foundMetadata = $classMetadata;
    }
}

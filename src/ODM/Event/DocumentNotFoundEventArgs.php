<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

/**
 * Provides event arguments for the documentNotFound event.
 */
final class DocumentNotFoundEventArgs extends LifecycleEventArgs
{
    private bool $disableException = false;

    public function __construct(object $document, DocumentManager $dm, private readonly mixed $identifier)
    {
        parent::__construct($document, $dm);
    }

    /**
     * Disable the throwing of an exception
     *
     * This method indicates to the proxy initializer that the missing document
     * has been handled and no exception should be thrown. This can't be reset.
     */
    public function disableException(bool $disableException = true): void
    {
        $this->disableException = $disableException;
    }

    /**
     * Retrieve associated identifier.
     */
    public function getIdentifier(): mixed
    {
        return $this->identifier;
    }

    /**
     * Indicates whether the proxy initialization exception is disabled.
     */
    public function isExceptionDisabled(): bool
    {
        return $this->disableException;
    }
}

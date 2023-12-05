<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

/**
 * Class that holds event arguments for a preLoad event.
 */
final class PreLoadEventArgs extends LifecycleEventArgs
{
    private array $data;

    public function __construct(object $document, DocumentManager $dm, array &$data)
    {
        parent::__construct($document, $dm);

        $this->data =& $data;
    }

    /**
     * Get the array of data to be loaded and hydrated.
     */
    public function &getData(): array
    {
        return $this->data;
    }
}

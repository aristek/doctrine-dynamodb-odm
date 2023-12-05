<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Hydrator;

/**
 * The HydratorInterface defines methods all hydrator need to implement
 */
interface HydratorInterface
{
    /**
     * Hydrate array of DynamoDB document data into the given document object.
     */
    public function hydrate(object $document, array $data, array $hints = []): array;
}

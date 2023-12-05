<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

/**
 * Types implementing this interface can have the `increment` storage strategy.
 */
interface Incrementable
{
    /**
     * Calculates PHP-based difference between given values.
     */
    public function diff(mixed $old, mixed $new): mixed;
}

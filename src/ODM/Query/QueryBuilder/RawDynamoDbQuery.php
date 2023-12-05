<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;

use ArrayAccess;
use ArrayObject;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_filter;
use function count;
use function is_bool;
use function is_numeric;

final class RawDynamoDbQuery implements IteratorAggregate, ArrayAccess, Countable
{
    /**
     * 'Scan', 'Query', etc.
     */
    public ?string $op = null;

    /**
     * The query body being sent to AWS
     */
    public array $query;

    public function __construct($op, $query)
    {
        $this->op = $op;
        $this->query = $query;
    }

    public function count(): int
    {
        return count($this->internal());
    }

    /**
     * Perform any final clean up.
     * Remove any empty values to avoid errors.
     */
    public function finalize(): self
    {
        $this->query = array_filter($this->query, static function ($value) {
            return !empty($value) || is_bool($value) || is_numeric($value);
        });

        return $this;
    }

    public function getIterator(): Traversable|ArrayObject
    {
        return new ArrayObject($this->internal());
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->internal()[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->internal()[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->internal()[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->internal()[$offset]);
    }

    /**
     * For backward compatibility, previously we use array to represent the raw query
     */
    private function internal(): array
    {
        return [$this->op, $this->query];
    }
}

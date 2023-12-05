<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

final class Placeholder
{
    private int $counter;

    public function __construct()
    {
        $this->reset();
    }

    public function next(): string
    {
        ++$this->counter;

        return "a{$this->counter}";
    }

    public function reset(): self
    {
        $this->counter = 0;

        return $this;
    }
}

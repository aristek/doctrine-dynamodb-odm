<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\ConditionAnalyzer;

final class Index
{
    public string $hash;

    public string $name;

    public string $range;

    public function __construct(string $name, string $hash, string $range)
    {
        $this->name = $name;
        $this->hash = $hash;
        $this->range = $range;
    }

    public function columns(): array
    {
        $columns = [];

        if ($this->hash) {
            $columns[] = $this->hash;
        }

        if ($this->range) {
            $columns[] = $this->range;
        }

        return $columns;
    }

    public function isComposite(): bool
    {
        return isset($this->hash, $this->range);
    }
}

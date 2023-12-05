<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

use function array_keys;

final class ExpressionAttributeValues
{
    protected array $mapping;

    protected string $prefix;

    public function __construct(string $prefix = ':')
    {
        $this->reset();
        $this->prefix = $prefix;
    }

    public function all(): array
    {
        return $this->mapping;
    }

    public function get($placeholder)
    {
        return $this->mapping[$placeholder];
    }

    public function placeholder(string $name): string
    {
        $placeholder = "$this->prefix$name";
        if (isset($this->mapping[$placeholder])) {
            return $placeholder;
        }

        return $name;
    }

    public function placeholders(): array
    {
        return array_keys($this->mapping);
    }

    public function reset(): self
    {
        $this->mapping = [];

        return $this;
    }

    public function set(string $placeholder, mixed $value): void
    {
        $this->mapping["$this->prefix$placeholder"] = $value;
    }
}

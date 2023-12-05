<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

use function array_key_last;
use function array_keys;
use function array_merge;
use function ctype_digit;
use function explode;
use function rtrim;
use function str_contains;

final class ExpressionAttributeNames
{
    protected array $mapping;

    protected array $nested;

    /**
     * @var string
     */
    protected mixed $prefix;

    public function __construct($prefix = '#')
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

    public function placeholder($name)
    {
        if ($this->isNested($name)) {
            $nestedPlaceholder = '';
            $parts = $this->explodeNestedField($name);

            foreach ($parts as $index => $item) {
                if (ctype_digit($item)) {
                    $nestedPlaceholder = rtrim($nestedPlaceholder, '.');
                    $nestedPlaceholder .= "[$item].";
                    continue;
                }

                $placeholder = "$this->prefix$item";

                if (isset($this->mapping[$placeholder])) {
                    $nestedPlaceholder .= $placeholder.($index === array_key_last($parts) ? '' : '.');
                }
            }

            return $nestedPlaceholder;
        }

        $placeholder = "$this->prefix$name";
        if (isset($this->mapping[$placeholder])) {
            return $placeholder;
        }

        return $name;
    }

    public function placeholders(): array
    {
        return array_merge(array_keys($this->mapping), $this->nested);
    }

    public function reset(): self
    {
        $this->mapping = [];
        $this->nested = [];

        return $this;
    }

    public function set($name): void
    {
        if ($this->isNested($name)) {
            $this->nested[] = $name;

            $parts = $this->explodeNestedField($name);
            foreach ($parts as $item) {
                if (ctype_digit($item)) {
                    continue;
                }

                $this->mapping["$this->prefix$item"] = $item;
            }

            return;
        }

        $this->mapping["$this->prefix$name"] = $name;
    }

    private function explodeNestedField(string $field): array
    {
        return explode('.', $field);
    }

    private function isNested($name): bool
    {
        return str_contains($name, '.') || (str_contains($name, '[') && str_contains($name, ']'));
    }
}

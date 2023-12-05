<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use ArrayAccess;
use JsonException;
use LogicException;
use OutOfBoundsException;
use RuntimeException;
use function is_array;
use function is_bool;
use function is_null;
use function print_r;

final class DynamoDbItem implements ArrayAccess
{
    protected array $data = [];

    public static function createFromArray(array $normal_value, $known_types = []): self
    {
        $ret = new self;

        foreach ($normal_value as $k => &$v) {
            $ret->data[$k] = self::toTypedValue($v, $known_types[$k] ?? null);
        }

        return $ret;
    }

    public static function createFromTypedArray(array $typed_value): self
    {
        $ret = new self;
        $ret->data = $typed_value;

        return $ret;
    }

    protected static function determineAttributeType(&$v): string
    {
        if (is_string($v)) {
            return DynamoDbIndex::ATTRIBUTE_TYPE_STRING;
        }

        if (is_int($v) || is_float($v)) {
            return DynamoDbIndex::ATTRIBUTE_TYPE_NUMBER;
        }

        if (is_bool($v)) {
            return DynamoDbIndex::ATTRIBUTE_TYPE_BOOL;
        }

        if (is_null($v)) {
            return DynamoDbIndex::ATTRIBUTE_TYPE_NULL;
        }

        if (is_array($v)) {
            $idx = 0;
            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach ($v as $k => &$vv) {
                if ($k !== $idx) {
                    return DynamoDbIndex::ATTRIBUTE_TYPE_MAP;
                }
                $idx++;
            }

            return DynamoDbIndex::ATTRIBUTE_TYPE_LIST;
        }

        throw new LogicException("Cannot determine type of attribute: ".print_r($v, true));
    }

    protected static function toTypedValue(&$v, $type = null): array
    {
        if (!$type) {
            $type = self::determineAttributeType($v);
        }

        switch ($type) {
            case DynamoDbIndex::ATTRIBUTE_TYPE_STRING:
            {
                if ((string) $v !== '') {
                    return [$type => (string) $v];
                }

                return [DynamoDbIndex::ATTRIBUTE_TYPE_NULL => true];
            }
            case DynamoDbIndex::ATTRIBUTE_TYPE_BINARY:
            {
                if (!$v) {
                    return [DynamoDbIndex::ATTRIBUTE_TYPE_NULL => true];
                }

                return [$type => base64_encode($v)];
            }
            case DynamoDbIndex::ATTRIBUTE_TYPE_BOOL:
                return [$type => (bool) $v];
            case DynamoDbIndex::ATTRIBUTE_TYPE_NULL:
                return [$type => true];
            case DynamoDbIndex::ATTRIBUTE_TYPE_NUMBER:
            {
                if (!is_numeric($v)) {
                    return [$type => "0"];
                }
                if ((int) $v === $v) {
                    return [$type => (string) $v];
                }

                return [$type => (string) (float) $v];
            }
            case DynamoDbIndex::ATTRIBUTE_TYPE_LIST:
            case DynamoDbIndex::ATTRIBUTE_TYPE_MAP:
            {
                $children = [];
                foreach ($v as $k => &$vv) {
                    $children[$k] = self::toTypedValue($vv);
                }

                return [$type => $children];
            }
            default:
            {
                $const_key = __CLASS__."::ATTRIBUTE_TYPE_".strtoupper($type);

                if (defined($const_key)) {
                    $type = constant($const_key);

                    return self::toTypedValue($v, $type);
                }

                throw new RuntimeException("Unknown type for dynamodb item, value = $v");
            }
        }
    }

    /**
     * @throws JsonException
     */
    protected static function toUntypedValue(&$v): float|int|bool|array|string|null
    {
        if (!is_array($v) || count($v) !== 1) {
            throw new LogicException("Value used is not typed value, value = ".json_encode($v, JSON_THROW_ON_ERROR));
        }

        $value = reset($v);
        $type = key($v);

        switch ($type) {
            case DynamoDbIndex::ATTRIBUTE_TYPE_STRING:
                return (string) $value;
            case DynamoDbIndex::ATTRIBUTE_TYPE_BINARY:
                return base64_decode($value);
            case DynamoDbIndex::ATTRIBUTE_TYPE_BOOL:
                return (bool) $value;
            case DynamoDbIndex::ATTRIBUTE_TYPE_NULL:
                return null;
            case DynamoDbIndex::ATTRIBUTE_TYPE_NUMBER:
                if ((int) $value === $value) {
                    return $value;
                }

                return (float) $value;
            case DynamoDbIndex::ATTRIBUTE_TYPE_LIST:
            case DynamoDbIndex::ATTRIBUTE_TYPE_MAP:
                if (!is_array($value)) {
                    throw new LogicException(
                        "The value is expected to be an array! $value = ".json_encode($v, JSON_THROW_ON_ERROR)
                    );
                }

                $ret = [];
                foreach ($value as $k => &$vv) {
                    $ret[$k] = self::toUntypedValue($vv);
                }

                return $ret;
            default:
                throw new LogicException("Type $type is not recognized!");
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @throws JsonException
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!array_key_exists($offset, $this->data)) {
            throw new OutOfBoundsException("Attribute $offset does not exist in DynamoDbItem!");
        }

        return self::toUntypedValue($this->data[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = self::toTypedValue($value);
    }

    public function offsetUnset(mixed $offset): void
    {
        if (!array_key_exists($offset, $this->data)) {
            throw new OutOfBoundsException("Attribute $offset does not exist in DynamoDbItem!");
        }

        unset($this->data[$offset]);
    }

    /**
     * @throws JsonException
     */
    public function toArray(): array
    {
        $ret = [];
        foreach ($this->data as $k => &$v) {
            $ret[$k] = self::toUntypedValue($v);
        }

        return $ret;
    }
}

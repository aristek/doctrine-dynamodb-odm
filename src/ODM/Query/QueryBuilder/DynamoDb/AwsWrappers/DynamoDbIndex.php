<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

final class DynamoDbIndex
{
    public const ATTRIBUTE_TYPE_BINARY = 'B';
    public const ATTRIBUTE_TYPE_BOOL = 'BOOL';
    public const ATTRIBUTE_TYPE_LIST = 'L';
    public const ATTRIBUTE_TYPE_MAP = 'M';
    public const ATTRIBUTE_TYPE_NULL = 'NULL';
    public const ATTRIBUTE_TYPE_NUMBER = 'N';
    public const ATTRIBUTE_TYPE_STRING = 'S';
    public const PRIMARY_INDEX = true;
    public const PROJECTION_TYPE_ALL = "ALL";
    public const PROJECTION_TYPE_INCLUDE = "INCLUDE";
    public const PROJECTION_TYPE_KEYS_ONLY = "KEYS_ONLY";

    protected string $hashKey;

    protected string $hashKeyType = self::ATTRIBUTE_TYPE_STRING;

    protected string $name = '';

    protected array $projectedAttributes = [];

    protected string $projectionType = self::PROJECTION_TYPE_ALL;

    protected ?string $rangeKey = null;

    protected string $rangeKeyType = self::ATTRIBUTE_TYPE_STRING;

    /**
     * @return string[]
     */
    public static function getSupportedProjectionTypes(): array
    {
        return [self::PROJECTION_TYPE_ALL, self::PROJECTION_TYPE_INCLUDE, self::PROJECTION_TYPE_KEYS_ONLY];
    }

    public function __construct(
        $hashKey,
        $hashKeyType = self::ATTRIBUTE_TYPE_STRING,
        $rangeKey = null,
        $rangeKeyType = self::ATTRIBUTE_TYPE_STRING,
        $projectionType = self::PROJECTION_TYPE_ALL,
        $projectedAttributes = []
    ) {
        $this->hashKey = $hashKey;
        $this->hashKeyType = $hashKeyType;
        $this->rangeKey = $rangeKey;
        $this->rangeKeyType = $rangeKeyType;
        $this->projectionType = $projectionType;
        $this->projectedAttributes = $projectedAttributes;
    }

    public function equals(DynamoDbIndex $other): bool
    {
        if ($this->projectionType !== $other->projectionType) {
            return false;
        }

        $projectedAttr = $this->projectedAttributes;
        $projectedAttrOther = $other->projectedAttributes;

        asort($projectedAttr);
        asort($projectedAttrOther);

        $projectedAttr = array_values($projectedAttr);
        $projectedAttrOther = array_values($projectedAttrOther);

        if ($this->projectionType === self::PROJECTION_TYPE_INCLUDE
            && (array_diff_assoc($projectedAttr, $projectedAttrOther)
                || array_diff_assoc($projectedAttrOther, $projectedAttr))
        ) {
            return false;
        }

        if ($this->hashKey !== $other->hashKey || $this->hashKeyType !== $other->hashKeyType) {
            return false;
        }

        if (($this->rangeKey || $other->rangeKey)
            && ($this->rangeKey !== $other->rangeKey || $this->rangeKeyType !== $other->rangeKeyType)
        ) {
            return false;
        }

        return true;
    }

    public function getAttributeDefinitions(bool $keyAsName = true): array
    {
        $attrDef = [
            $this->hashKey => [
                "AttributeName" => $this->hashKey,
                "AttributeType" => $this->hashKeyType,
            ],
        ];

        if ($this->rangeKey) {
            $attrDef[$this->rangeKey] = [
                "AttributeName" => $this->rangeKey,
                "AttributeType" => $this->rangeKeyType,
            ];
        }

        if (!$keyAsName) {
            $attrDef = array_values($attrDef);
        }

        return $attrDef;
    }

    public function getHashKey(): string
    {
        return $this->hashKey;
    }

    public function getHashKeyType(): string
    {
        return $this->hashKeyType;
    }

    public function getKeySchema(): array
    {
        $keySchema = [
            [
                "AttributeName" => $this->hashKey,
                "KeyType"       => "HASH",
            ],
        ];

        if ($this->rangeKey) {
            $keySchema[] = [
                "AttributeName" => $this->rangeKey,
                "KeyType"       => "RANGE",
            ];
        }

        return $keySchema;
    }

    public function getName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $result = $this->hashKey."-".$this->rangeKey."-index";
        $result = preg_replace('/(?!^)([A-Z])([a-z0-9])/', '_$1$2', $result);
        $result = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $result);
        $result = preg_replace('/_+/', '_', $result);
        $result = trim($result, "_");
        $result = strtolower($result);
        $this->name = $result;

        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProjectedAttributes(): array
    {
        return $this->projectedAttributes;
    }

    public function getProjection(): array
    {
        $projection = [
            "ProjectionType" => $this->projectionType,
        ];

        if ($this->projectionType === self::PROJECTION_TYPE_INCLUDE) {
            $projection["NonKeyAttributes"] = $this->projectedAttributes;
        }

        return $projection;
    }

    public function getProjectionType(): string
    {
        return $this->projectionType;
    }

    public function getRangeKey(): ?string
    {
        return $this->rangeKey;
    }

    public function getRangeKeyType(): string
    {
        return $this->rangeKeyType;
    }
}

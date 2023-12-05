<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb;

use function array_keys;
use function array_search;
use function in_array;
use function strtolower;
use function strtoupper;

final class ComparisonOperator
{
    public const BEGINS_WITH = 'BEGINS_WITH';
    public const BETWEEN = 'BETWEEN';
    public const CONTAINS = 'CONTAINS';
    public const EQ = 'EQ';
    public const GE = 'GE';
    public const GT = 'GT';
    public const IN = 'IN';
    public const LE = 'LE';
    public const LT = 'LT';
    public const NE = 'NE';
    public const NOT_CONTAINS = 'NOT_CONTAINS';
    public const NOT_NULL = 'NOT_NULL';
    public const NULL = 'NULL';

    public static function getDynamoDbOperator(string $operator): string
    {
        $mapping = self::getOperatorMapping();

        $operator = strtolower($operator);

        return $mapping[$operator];
    }

    public static function getOperatorMapping(): array
    {
        return [
            '='            => self::EQ,
            '>'            => self::GT,
            '>='           => self::GE,
            '<'            => self::LT,
            '<='           => self::LE,
            'in'           => self::IN,
            '!='           => self::NE,
            'begins_with'  => self::BEGINS_WITH,
            'between'      => self::BETWEEN,
            'not_contains' => self::NOT_CONTAINS,
            'contains'     => self::CONTAINS,
            'null'         => self::NULL,
            'not_null'     => self::NOT_NULL,
        ];
    }

    public static function getQueryDynamoDbOperator(string $operator): string
    {
        $mapping = self::getOperatorMapping();

        $operator = strtoupper($operator);

        return array_search($operator, $mapping, true);
    }

    public static function getQuerySupportedOperators(bool $isRangeKey = false): array
    {
        if ($isRangeKey) {
            return [
                self::EQ,
                self::LE,
                self::LT,
                self::GE,
                self::GT,
                self::BEGINS_WITH,
                self::BETWEEN,
            ];
        }

        return [self::EQ];
    }

    public static function getSupportedOperators(): array
    {
        return array_keys(self::getOperatorMapping());
    }

    public static function is(string $op, string $dynamoDbOperator): bool
    {
        $mapping = self::getOperatorMapping();

        return $mapping[strtolower($op)] === $dynamoDbOperator;
    }

    public static function isValidOperator(string $operator): bool
    {
        $operator = strtolower($operator);

        $mapping = self::getOperatorMapping();

        return isset($mapping[$operator]);
    }

    public static function isValidQueryDynamoDbOperator(string $dynamoDbOperator, bool $isRangeKey = false): bool
    {
        return in_array($dynamoDbOperator, self::getQuerySupportedOperators($isRangeKey), true);
    }

    public static function isValidQueryOperator(string $operator, bool $isRangeKey = false): bool
    {
        $dynamoDbOperator = self::getDynamoDbOperator($operator);

        return self::isValidQueryDynamoDbOperator($dynamoDbOperator, $isRangeKey);
    }
}

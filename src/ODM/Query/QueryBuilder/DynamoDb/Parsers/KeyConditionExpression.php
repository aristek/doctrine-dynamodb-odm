<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\ComparisonOperator;
use Illuminate\Support\Arr;

final class KeyConditionExpression extends ConditionExpression
{
    protected function getSupportedOperators(): array
    {
        return Arr::only(self::OPERATORS, [
            ComparisonOperator::EQ,
            ComparisonOperator::LE,
            ComparisonOperator::LT,
            ComparisonOperator::GE,
            ComparisonOperator::GT,
            ComparisonOperator::BEGINS_WITH,
            ComparisonOperator::BETWEEN,
        ]);
    }
}

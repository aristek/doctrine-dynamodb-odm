<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

use function implode;

final class ProjectionExpression
{
    protected ExpressionAttributeNames $names;

    public function __construct(ExpressionAttributeNames $names)
    {
        $this->names = $names;
    }

    public function parse(array $columns): string
    {
        foreach ($columns as $column) {
            $this->names->set($column);
        }

        return implode(', ', $this->names->placeholders());
    }
}

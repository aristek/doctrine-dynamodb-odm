<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\ComparisonOperator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\DynamoDbManager;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Exception\NotSupportedException;
use Illuminate\Support\Arr;
use function count;
use function implode;
use function sprintf;
use function strtoupper;

class ConditionExpression
{
    protected const OPERATORS = [
        ComparisonOperator::EQ           => '%s = :%s',
        ComparisonOperator::LE           => '%s <= :%s',
        ComparisonOperator::LT           => '%s < :%s',
        ComparisonOperator::GE           => '%s >= :%s',
        ComparisonOperator::GT           => '%s > :%s',
        ComparisonOperator::BEGINS_WITH  => 'begins_with(%s, :%s)',
        ComparisonOperator::BETWEEN      => '(%s BETWEEN :%s AND :%s)',
        ComparisonOperator::CONTAINS     => 'contains(%s, :%s)',
        ComparisonOperator::NOT_CONTAINS => 'NOT contains(%s, :%s)',
        ComparisonOperator::NULL         => 'attribute_not_exists(%s)',
        ComparisonOperator::NOT_NULL     => 'attribute_exists(%s)',
        ComparisonOperator::NE           => '%s <> :%s',
        ComparisonOperator::IN           => '%s IN (%s)',
    ];

    protected ExpressionAttributeNames $names;

    protected Placeholder $placeholder;

    protected ExpressionAttributeValues $values;

    private DynamoDbManager $dbManager;

    public function __construct(
        Placeholder $placeholder,
        ExpressionAttributeValues $values,
        ExpressionAttributeNames $names,
        DynamoDbManager $dbManager
    ) {
        $this->placeholder = $placeholder;
        $this->values = $values;
        $this->names = $names;
        $this->dbManager = $dbManager;
    }

    /**
     *     [
     *     'column' => 'name',
     *     'type' => 'EQ',
     *     'value' => 'foo',
     *     'boolean' => 'and',
     *     ]
     *
     * @throws NotSupportedException
     */
    public function parse(array $where): string
    {
        if (empty($where)) {
            return '';
        }

        $parsed = [];

        foreach ($where as $condition) {
            $boolean = Arr::get($condition, 'boolean');
            $value = Arr::get($condition, 'value');
            $type = Arr::get($condition, 'type');

            $prefix = '';

            if (count($parsed) > 0) {
                $prefix = strtoupper($boolean).' ';
            }

            if ($type === 'Nested') {
                $parsed[] = $prefix.$this->parseNestedCondition($value);
                continue;
            }

            $parsed[] = $prefix.$this->parseCondition(
                    Arr::get($condition, 'column'),
                    $type,
                    $value
                );
        }

        return implode(' ', $parsed);
    }

    public function reset(): void
    {
        $this->placeholder->reset();
        $this->names->reset();
        $this->values->reset();
    }

    protected function getSupportedOperators(): array
    {
        return static::OPERATORS;
    }

    protected function parseBetweenCondition($name, $value, $template): string
    {
        $first = $this->placeholder->next();

        $second = $this->placeholder->next();

        $this->values->set($first, $this->dbManager->marshalValue($value[0]));
        $this->values->set($second, $this->dbManager->marshalValue($value[1]));

        return sprintf($template, $this->names->placeholder($name), $first, $second);
    }

    protected function parseCondition($name, $operator, $value): string
    {
        $operators = $this->getSupportedOperators();

        if (empty($operators[$operator])) {
            throw new NotSupportedException("$operator is not supported");
        }

        $template = $operators[$operator];

        $this->names->set($name);

        if ($operator === ComparisonOperator::BETWEEN) {
            return $this->parseBetweenCondition($name, $value, $template);
        }

        if ($operator === ComparisonOperator::IN) {
            return $this->parseInCondition($name, $value, $template);
        }

        if ($operator === ComparisonOperator::NULL || $operator === ComparisonOperator::NOT_NULL) {
            return $this->parseNullCondition($name, $template);
        }

        $placeholder = $this->placeholder->next();

        $this->values->set($placeholder, $this->dbManager->marshalValue($value));

        return sprintf($template, $this->names->placeholder($name), $placeholder);
    }

    protected function parseInCondition($name, $value, $template): string
    {
        $valuePlaceholders = [];

        foreach ($value as $item) {
            $placeholder = $this->placeholder->next();

            $valuePlaceholders[] = ":".$placeholder;

            $this->values->set($placeholder, $this->dbManager->marshalValue($item));
        }

        return sprintf($template, $this->names->placeholder($name), implode(', ', $valuePlaceholders));
    }

    protected function parseNestedCondition(array $conditions): string
    {
        return '('.$this->parse($conditions).')';
    }

    protected function parseNullCondition($name, $template): string
    {
        return sprintf($template, $this->names->placeholder($name));
    }
}

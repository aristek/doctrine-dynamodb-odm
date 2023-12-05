<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\DynamoDbManager;
use function implode;
use function str_replace;

final class UpdateExpression
{
    public function __construct(
        public ExpressionAttributeNames $names,
        public ExpressionAttributeValues $values,
        private readonly DynamoDbManager $dbManager,
    ) {
    }

    public function remove(array $attributes): string
    {
        foreach ($attributes as $attribute) {
            $this->names->set($attribute);
        }

        return 'REMOVE '.implode(', ', $this->names->placeholders());
    }

    public function reset(): void
    {
        $this->names->reset();
    }

    public function update(array $attributes): string
    {
        $expression = 'SET ';

        foreach ($attributes as $attribute => $value) {
            $this->names->set($attribute);
            $attributePlaceholder = str_replace('.', '_', $attribute);
            $this->values->set($attributePlaceholder, $this->dbManager->marshalValue($value));

            $expression .= $this->names->placeholder($attribute).' = '.$this->values->placeholder($attributePlaceholder);
            $expression .= $attribute === array_key_last($attributes) ? '' : ', ';
        }

        return $expression;
    }
}

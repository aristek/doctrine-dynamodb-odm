<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\ExpressionAttributeNames;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\ExpressionAttributeValues;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\FilterExpression;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\KeyConditionExpression;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\Placeholder;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\ProjectionExpression;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\Parsers\UpdateExpression;

trait HasParsers
{
    protected ExpressionAttributeNames $expressionAttributeNames;

    protected ExpressionAttributeValues $expressionAttributeValues;

    protected FilterExpression $filterExpression;

    protected KeyConditionExpression $keyConditionExpression;

    protected Placeholder $placeholder;

    protected ProjectionExpression $projectionExpression;

    protected UpdateExpression $updateExpression;

    public function resetExpressions(): void
    {
        $this->filterExpression->reset();
        $this->keyConditionExpression->reset();
        $this->updateExpression->reset();
    }

    public function setupExpressions(DynamoDbManager $dbManager): void
    {
        $this->placeholder = new Placeholder();

        $this->expressionAttributeNames = new ExpressionAttributeNames();

        $this->expressionAttributeValues = new ExpressionAttributeValues();

        $this->keyConditionExpression = new KeyConditionExpression(
            $this->placeholder,
            $this->expressionAttributeValues,
            $this->expressionAttributeNames,
            $dbManager
        );

        $this->filterExpression = new FilterExpression(
            $this->placeholder,
            $this->expressionAttributeValues,
            $this->expressionAttributeNames,
            $dbManager
        );

        $this->projectionExpression = new ProjectionExpression($this->expressionAttributeNames);

        $this->updateExpression = new UpdateExpression(
            $this->expressionAttributeNames,
            $this->expressionAttributeValues,
            $dbManager
        );
    }
}

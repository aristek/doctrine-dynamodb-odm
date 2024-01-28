<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDbClientInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\RawDynamoDbQuery;
use Aws\DynamoDb\DynamoDbClient;
use BadMethodCallException;
use Illuminate\Support\Str;
use function array_reverse;
use function current;
use function explode;
use function sprintf;

/**
 * Methods are in the form of `set<key_name>`, where `<key_name>`
 * is the key name of the query body to be sent.
 *
 * For example, to build a query:
 * [
 *     'AttributeDefinitions' => ...,
 *     'GlobalSecondaryIndexUpdates' => ...
 *     'TableName' => ...
 * ]
 *
 * Do:
 *
 * $query = $query->setAttributeDefinitions(...)->setGlobalSecondaryIndexUpdates(...)->setTableName(...);
 *
 * When ready:
 *
 * $query->prepare()->updateTable();
 *
 * Common methods:
 *
 * @method QueryBuilder setExpressionAttributeNames(array $mapping)
 * @method QueryBuilder setExpressionAttributeValues(array $mapping)
 * @method QueryBuilder setFilterExpression(string $expression)
 * @method QueryBuilder setKeyConditionExpression(string $expression)
 * @method QueryBuilder setProjectionExpression(string $expression)
 * @method QueryBuilder setUpdateExpression(string $expression)
 * @method QueryBuilder setAttributeUpdates(array $updates)
 * @method QueryBuilder setConsistentRead(bool $consistent)
 * @method QueryBuilder setScanIndexForward(bool $forward)
 * @method QueryBuilder setExclusiveStartKey(mixed $key)
 * @method QueryBuilder setReturnValues(string $type)
 * @method QueryBuilder setRequestItems(array $items)
 * @method QueryBuilder setTableName(string $table)
 * @method QueryBuilder setIndexName(string $index)
 * @method QueryBuilder setSelect(string $select)
 * @method QueryBuilder setItem(array $item)
 * @method QueryBuilder setKeys(array $keys)
 * @method QueryBuilder setLimit(int $limit)
 * @method QueryBuilder setKey(array $key)
 */
final class QueryBuilder
{
    /**
     * Query body to be sent to AWS
     */
    public array $query = [];

    private DynamoDbClientInterface $service;

    public function __construct(DynamoDbClientInterface $service)
    {
        $this->service = $service;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (Str::startsWith($method, 'set')) {
            $key = array_reverse(explode('set', $method, 2))[0];

            if ($value = current($parameters)) {
                $this->query[$key] = $value;
            }

            return $this;
        }

        throw new BadMethodCallException(
            sprintf(
                'Method %s::%s does not exist.',
                self::class,
                $method
            )
        );
    }

    public function hydrate(array $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function prepare(DynamoDbClient $client = null): ExecutableQuery
    {
        $raw = new RawDynamoDbQuery(null, $this->query);

        return new ExecutableQuery($client ?: $this->service->getClient(), $raw->finalize()->query);
    }

    public function setExpressionAttributeName($placeholder, $name): self
    {
        $this->query['ExpressionAttributeNames'][$placeholder] = $name;

        return $this;
    }

    public function setExpressionAttributeValue($placeholder, $value): self
    {
        $this->query['ExpressionAttributeValues'][$placeholder] = $value;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDbClientInterface;
use Aws\DynamoDb\BinaryValue;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\NumberValue;
use Aws\DynamoDb\SetValue;
use stdClass;

final class DynamoDbManager
{
    use HasParsers;

    private Marshaler $marshaler;

    private DynamoDbClientInterface $service;

    public function __construct(DynamoDbClientInterface $service)
    {
        $this->service = $service;
        $this->marshaler = $service->getMarshaler();

        $this->setupExpressions($this);
    }

    public function client(): DynamoDbClient
    {
        return $this->service->getClient();
    }

    public function deleteOne(array $criteria, string $table): bool
    {
        $result = $this
            ->table($table)
            ->setKey($this->marshalItem($criteria))
            ->prepare($this->client())
            ->deleteItem();

        return $result->toArray()['@metadata.statusCode'] ?? null === 200;
    }

    public function insertMany(array $inserts, string $table): void
    {
        foreach ($inserts as $insert) {
            $this->insertOne($insert, $table);
        }
    }

    public function insertOne(array $insert, string $table): void
    {
        $this
            ->table($table)
            ->setItem($this->marshalItem($insert))
            ->prepare($this->client())
            ->putItem();
    }

    public function marshalItem($item): array
    {
        return $this->marshaler->marshalItem($item);
    }

    public function marshalValue($value): ?array
    {
        return $this->marshaler->marshalValue($value);
    }

    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->service);
    }

    public function table(string $table): QueryBuilder
    {
        return $this->newQuery()->setTableName($table);
    }

    public function unmarshalItem($item): array|SetValue|int|BinaryValue|stdClass|NumberValue|null
    {
        return $this->marshaler->unmarshalItem($item);
    }

    public function unmarshalValue($value)
    {
        return $this->marshaler->unmarshalValue($value);
    }

    public function updateOne(array $id, array $attributes, string $table): bool
    {
        $result = $this
            ->table($table)
            ->setKey($this->marshalItem($id))
            ->setUpdateExpression($this->updateExpression->update($attributes))
            ->setExpressionAttributeNames($this->expressionAttributeNames->all())
            ->setExpressionAttributeValues($this->expressionAttributeValues->all())
            ->setReturnValues('UPDATED_NEW')
            ->prepare($this->client())
            ->updateItem();

        return $result->toArray()['@metadata.statusCode'] ?? null === 200;
    }
}

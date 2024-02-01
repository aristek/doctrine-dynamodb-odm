<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb;

use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDbClientInterface;
use Aws\DynamoDb\BinaryValue;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\NumberValue;
use Aws\DynamoDb\SetValue;
use Exception;
use RuntimeException;
use stdClass;
use function json_encode;
use function sprintf;
use const JSON_THROW_ON_ERROR;

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

    /**
     * @throws Exception
     */
    public function deleteOne(array $criteria, string $table): bool
    {
        $this->resetExpressions();

        $query = null;

        try {
            $query = $this
                ->table($table)
                ->setKey($this->marshalItem($criteria))
                ->prepare($this->client());

            $result = $query->deleteItem();
        } catch (Exception $exception) {
            throw new RuntimeException(
                sprintf(
                    '%s, query: %s',
                    $exception->getMessage(),
                    json_encode($query?->query ?: [], JSON_THROW_ON_ERROR)
                )
            );
        }

        return $result->toArray()['@metadata.statusCode'] ?? null === 200;
    }

    public function insertMany(array $inserts, string $table): void
    {
        foreach ($inserts as $insert) {
            $this->insertOne($insert, $table);
        }
    }

    /**
     * @throws Exception
     */
    public function insertOne(array $insert, string $table): bool
    {
        $this->resetExpressions();

        $query = null;
        try {
            $query = $this
                ->table($table)
                ->setItem($this->marshalItem($insert))
                ->prepare($this->client());

            $result = $query->putItem();
        } catch (Exception $exception) {
            throw new RuntimeException(
                sprintf(
                    '%s, query: %s',
                    $exception->getMessage(),
                    json_encode($query?->query ?: [], JSON_THROW_ON_ERROR)
                )
            );
        }

        return $result->toArray()['@metadata.statusCode'] ?? null === 200;
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

    /**
     * @throws Exception
     */
    public function updateOne(array $id, array $attributes, string $table): bool
    {
        $this->resetExpressions();

        $query = null;
        try {
            $query = $this
                ->table($table)
                ->setKey($this->marshalItem($id))
                ->setUpdateExpression($this->updateExpression->update($attributes))
                ->setExpressionAttributeNames($this->expressionAttributeNames->all())
                ->setExpressionAttributeValues($this->expressionAttributeValues->all())
                ->setReturnValues('UPDATED_NEW')
                ->prepare($this->client());

            $result = $query->updateItem();
        } catch (Exception $exception) {
            throw new RuntimeException(
                sprintf(
                    '%s, query: %s',
                    $exception->getMessage(),
                    json_encode($query?->query ?: [], JSON_THROW_ON_ERROR)
                )
            );
        }

        return $result->toArray()['@metadata.statusCode'] ?? null === 200;
    }
}

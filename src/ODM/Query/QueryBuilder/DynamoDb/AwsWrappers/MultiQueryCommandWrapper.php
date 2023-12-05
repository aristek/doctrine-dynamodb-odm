<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\Result;
use GuzzleHttp\Promise\Each;
use SplQueue;

final class MultiQueryCommandWrapper
{
    public function __invoke(
        DynamoDbClient $dbClient,
        string $tableName,
        callable $callback,
        string $hashKeyName,
        array $hashKeyValues,
        ?string $rangeKeyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string $indexName,
        $filterExpression,
        $evaluationLimit,
        $isConsistentRead,
        $isAscendingOrder,
        $concurrency,
        $projectedFields
    ): void {
        $fieldsMapping["#".$hashKeyName] = $hashKeyName;
        $keyConditions = sprintf(
            "#%s = :%s",
            $hashKeyName,
            $hashKeyName
        );

        if ($rangeKeyConditions) {
            $keyConditions .= " AND ".$rangeKeyConditions;
        }

        $concurrency = min($concurrency, count($hashKeyValues));

        $queue = new SplQueue();
        foreach ($hashKeyValues as $hashKeyValue) {
            $queue->push([$hashKeyValue, false]);
        }

        $stopped = false;

        $generator = static function () use (
            &$stopped,
            $dbClient,
            $tableName,
            $callback,
            $queue,
            $hashKeyName,
            $keyConditions,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $filterExpression,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        ) {
            while (!$stopped && !$queue->isEmpty()) {
                [$hashKeyValue, $lastKey] = $queue->shift();

                if ($lastKey === null) {
                    continue;
                }

                $paramsMapping[":".$hashKeyName] = $hashKeyValue;
                $asyncWrapper = new QueryAsyncCommandWrapper();
                $promise = $asyncWrapper(
                    $dbClient,
                    $tableName,
                    $keyConditions,
                    $fieldsMapping,
                    $paramsMapping,
                    $indexName,
                    $filterExpression,
                    $lastKey,
                    $evaluationLimit,
                    $isConsistentRead,
                    $isAscendingOrder,
                    false,
                    $projectedFields
                );

                yield $hashKeyValue => $promise;
            }
        };

        while (!$stopped && !$queue->isEmpty()) {
            /** @noinspection PhpUnusedParameterInspection */
            Each::ofLimit(
                $generator(),
                $concurrency,
                static function (Result $result, $hashKeyValue) use ($callback, $queue, &$stopped) {
                    $lastKey = $result['LastEvaluatedKey'] ?? null;
                    $items = $result['Items'] ?? [];

                    foreach ($items as $typedItem) {
                        $item = DynamoDbItem::createFromTypedArray($typedItem);

                        if (false === $callback($item->toArray())) {
                            $stopped = true;
                            break;
                        }
                    }
                    $queue->push([$hashKeyValue, $lastKey]);
                },
                static function (
                    DynamoDbException $reason,
                    /** @noinspection PhpUnusedParameterInspection */
                    $hashKeyValue
                ) {
                    throw $reason;
                }
            )->wait();
        }
    }
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\DynamoDb\DynamoDbClient;
use JsonException;

final class QueryCommandWrapper
{
    /**
     * @throws JsonException
     */
    public function __invoke(
        DynamoDbClient $dbClient,
        $tableName,
        callable $callback,
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName,
        $filterExpression,
        &$lastKey,
        $evaluationLimit,
        $isConsistentRead,
        $isAscendingOrder,
        $countOnly,
        $projectedFields
    ) {
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
            $countOnly,
            $projectedFields
        );
        $result = $promise->wait();
        $lastKey = $result['LastEvaluatedKey'] ?? null;

        if ($countOnly) {
            return $result['Count'];
        }

        $items = $result['Items'] ?? [];
        $ret = 0;
        foreach ($items as $typedItem) {
            $ret++;
            $item = DynamoDbItem::createFromTypedArray($typedItem);

            if (false === $callback($item->toArray())) {
                break;
            }
        }

        return $ret;
    }
}

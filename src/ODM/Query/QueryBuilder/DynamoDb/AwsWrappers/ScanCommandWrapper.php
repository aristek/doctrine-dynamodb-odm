<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;

final class ScanCommandWrapper
{
    public function __invoke(
        DynamoDbClient $dbClient,
        $tableName,
        callable $callback,
        $filterExpression,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName,
        &$lastKey,
        $evaluationLimit,
        $isConsistentRead,
        $isAscendingOrder,
        $countOnly,
        $projectedAttributes
    ) {
        $asyncCommandWrapper = new ScanAsyncCommandWrapper();
        $promise = $asyncCommandWrapper(
            $dbClient,
            $tableName,
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            0,
            1,
            $countOnly,
            $projectedAttributes
        );
        $promise->then(
            function (Result $result) use (&$lastKey, &$ret, $callback, $countOnly) {
                $lastKey = $result['LastEvaluatedKey'] ?? null;
                if ($countOnly) {
                    $ret = $result['Count'];
                } else {
                    $items = $result['Items'] ?? [];
                    $ret = 0;

                    foreach ($items as $typedItem) {
                        $ret++;
                        $item = DynamoDbItem::createFromTypedArray($typedItem);

                        if (false === $callback($item->toArray())) {
                            break;
                        }
                    }
                }
            }
        );

        $promise->wait();

        return $ret;
    }
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use GuzzleHttp\Promise\Utils;

final class ParallelScanCommandWrapper
{
    public function __invoke(
        DynamoDbClient $dbClient,
        $tableName,
        callable $callback,
        $filterExpression,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName,
        $evaluationLimit,
        $isConsistentRead,
        $isAscendingOrder,
        $totalSegments,
        $countOnly,
        $projectedAttributes
    ): int {
        $ret = 0;
        $stoppedByCallback = false;
        $lastKeys = [];
        $finished = 0;
        for ($i = 0; $i < $totalSegments; ++$i) {
            $lastKeys[$i] = null;
        }

        while (!$stoppedByCallback && $finished < $totalSegments) {
            $promises = [];
            foreach ($lastKeys as $i => $lastKey) {
                if ($finished === 0 || $lastKey) {
                    $asyncCommandWrapper = new ScanAsyncCommandWrapper();
                    $promise = $asyncCommandWrapper(
                        $dbClient,
                        $tableName,
                        $filterExpression,
                        $fieldsMapping,
                        $paramsMapping,
                        $indexName,
                        $lastKeys[$i],
                        $evaluationLimit,
                        $isConsistentRead,
                        $isAscendingOrder,
                        $i,
                        $totalSegments,
                        $countOnly,
                        $projectedAttributes
                    );
                    $promise->then(
                        function (Result $result) use (
                            &$lastKeys,
                            $i,
                            &$ret,
                            &$finished,
                            $callback,
                            $countOnly,
                            &$stoppedByCallback
                        ) {
                            if ($stoppedByCallback) {
                                return;
                            }

                            $lastKeys[$i] = $result['LastEvaluatedKey'] ?? null;
                            if ($lastKeys[$i] === null) {
                                $finished++;
                            }

                            if ($countOnly) {
                                $ret += $result['Count'];
                            } else {
                                $items = $result['Items'] ?? [];
                                foreach ($items as $typedItem) {
                                    $item = DynamoDbItem::createFromTypedArray($typedItem);

                                    if (false === $callback($item->toArray(), $i)) {
                                        $stoppedByCallback = true;

                                        break;
                                    }
                                }

                                $ret += count($items);
                            }
                        }
                    );
                    $promises[] = $promise;
                }
            }
            if ($promises) {
                Utils::all($promises)->wait();
            }
        }

        return $ret;
    }
}

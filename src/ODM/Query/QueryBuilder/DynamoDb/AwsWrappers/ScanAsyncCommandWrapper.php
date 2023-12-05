<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\DynamoDb\DynamoDbClient;
use GuzzleHttp\Promise\Promise;
use InvalidArgumentException;
use function array_key_exists;
use function implode;

final class ScanAsyncCommandWrapper
{
    public function __invoke(
        DynamoDbClient $dbClient,
        $tableName,
        $filterExpression,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName,
        &$lastKey,
        $evaluationLimit,
        $isConsistentRead,
        $isAscendingOrder,
        $segment,
        $totalSegments,
        $countOnly,
        $projectedFields
    ): Promise {
        $requestArgs = [
            "TableName"        => $tableName,
            'ConsistentRead'   => $isConsistentRead,
            'ScanIndexForward' => $isAscendingOrder,
        ];

        if ($countOnly) {
            $requestArgs['Select'] = "COUNT";
        } else {
            if ($projectedFields) {
                $requestArgs['Select'] = "SPECIFIC_ATTRIBUTES";

                foreach ($projectedFields as $idx => $field) {
                    $projectedFields[$idx] = $escaped = '#'.$field;

                    if (array_key_exists($escaped, $fieldsMapping) && $fieldsMapping[$escaped] !== $field) {
                        throw new InvalidArgumentException(
                            "Field $field is used in projected fields and should not appear in fields mapping!"
                        );
                    }

                    $fieldsMapping[$escaped] = $field;
                }

                $requestArgs['ProjectionExpression'] = implode(', ', $projectedFields);
            }
        }

        if ($totalSegments > 1) {
            $requestArgs['Segment'] = $segment;
            $requestArgs['TotalSegments'] = $totalSegments;
        }

        if ($filterExpression) {
            $requestArgs['FilterExpression'] = $filterExpression;
        }

        if ($fieldsMapping) {
            $requestArgs['ExpressionAttributeNames'] = $fieldsMapping;
        }

        if ($paramsMapping) {
            $paramsItem = DynamoDbItem::createFromArray($paramsMapping);
            $requestArgs['ExpressionAttributeValues'] = $paramsItem->getData();
        }

        if ($indexName !== DynamoDbIndex::PRIMARY_INDEX) {
            $requestArgs['IndexName'] = $indexName;
        }

        if ($lastKey) {
            $requestArgs['ExclusiveStartKey'] = $lastKey;
        }

        if ($evaluationLimit) {
            $requestArgs['Limit'] = $evaluationLimit;
        }

        return $dbClient->scanAsync($requestArgs);
    }
}

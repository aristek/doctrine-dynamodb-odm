<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\CloudWatch\CloudWatchClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\Result;
use GuzzleHttp\Promise\Each;
use RuntimeException;
use UnexpectedValueException;
use function array_merge;
use function date;
use function implode;
use function preg_match;
use function preg_quote;
use function sprintf;
use function time;
use function usleep;

final class DynamoDbTable
{
    private static array $describeCache = [];

    private static array $describeTTLCache = [];

    protected array $attributeTypes = [];

    protected array $config = [];

    protected DynamoDbClient $dbClient;

    protected string $tableName;

    public function __construct(array $awsConfig, $tableName, $attributeTypes = [])
    {
        $this->dbClient = new DynamoDbClient(args: $awsConfig);
        $this->tableName = $tableName;
        $this->attributeTypes = $attributeTypes;
    }

    public function addGlobalSecondaryIndex(
        DynamoDbIndex $gsi,
        bool $provisionedBilling = true,
        int $readCapacity = 5,
        int $writeCapacity = 5
    ): void {
        if ($this->getGlobalSecondaryIndices(
            namePattern: sprintf(
                "/%s/",
                preg_quote(
                    str: $gsi->getName(),
                    delimiter: "/"
                )
            )
        )) {
            throw new RuntimeException(message: "Global Secondary Index exists, name = ".$gsi->getName());
        }

        $index = [
            'IndexName'  => $gsi->getName(),
            'KeySchema'  => $gsi->getKeySchema(),
            'Projection' => $gsi->getProjection(),
        ];

        if ($provisionedBilling) {
            $index['ProvisionedThroughput'] = [
                'ReadCapacityUnits'  => $readCapacity,
                'WriteCapacityUnits' => $writeCapacity,
            ];
        }

        $args = [
            'AttributeDefinitions'        => $gsi->getAttributeDefinitions(keyAsName: false),
            'GlobalSecondaryIndexUpdates' => [
                [
                    'Create' => $index,
                ],
            ],
            'TableName'                   => $this->tableName,
        ];
        $this->dbClient->updateTable($args);
    }

    public function batchDelete(
        array $objs,
        $concurrency = 10,
        $maxDelay = 15000
    ): void {
        $this->doBatchWrite(
            isPut: false,
            objs: $objs,
            concurrency: $concurrency,
            maxDelay: $maxDelay
        );
    }

    public function batchGet(
        array $keys,
        $isConsistentRead = false,
        $concurrency = 10,
        $projectedFields = [],
        $keyIsTyped = false,
        $retryDelay = 0,
        $maxDelay = 15000
    ): array {
        $mappingArgs = [];
        if ($projectedFields) {
            $fieldsMapping = [];

            foreach ($projectedFields as $idx => $field) {
                $projectedFields[$idx] = $escaped = '#'.$field;
                $fieldsMapping[$escaped] = $field;
            }

            $mappingArgs['ProjectionExpression'] = implode(', ', $projectedFields);
            $mappingArgs['ExpressionAttributeNames'] = $fieldsMapping;
        }

        $returnSet = [];
        $promises = [];
        $reads = [];
        $unprocessed = [];
        $flushCallback = function ($limit = 100) use (
            &$mappingArgs,
            &$promises,
            &$reads,
            $isConsistentRead,
        ) {
            if (count($reads) >= $limit) {
                $reqArgs = [
                    "RequestItems" => [
                        $this->tableName => array_merge(
                            $mappingArgs,
                            [
                                "Keys"           => $reads,
                                "ConsistentRead" => $isConsistentRead,
                            ]
                        ),
                    ],
                    //"ReturnConsumedCapacity" => "TOTAL",
                ];
                $promise = $this->dbClient->batchGetItemAsync($reqArgs);
                $promises[] = $promise;
                $reads = [];
            }
        };

        foreach ($keys as $key) {
            $keyItem = $keyIsTyped ? DynamoDbItem::createFromTypedArray($key) :
                DynamoDbItem::createFromArray($key, $this->attributeTypes);
            $req = $keyItem->getData();
            $reads[] = $req;
            $flushCallback();
        }

        $flushCallback(1);

        Each::ofLimit(
            iterable: $promises,
            concurrency: $concurrency,
            onFulfilled: function (Result $result) use (&$unprocessed, &$returnSet) {
                $unprocessedKeys = $result['UnprocessedKeys'];

                if (isset($unprocessedKeys[$this->tableName]["Keys"])) {
                    $currentUnprocessed = $unprocessedKeys[$this->tableName]["Keys"];

                    foreach ($currentUnprocessed as $action) {
                        $unprocessed[] = $action;
                    }
                }

                if (isset($result['Responses'][$this->tableName])) {
                    //mdebug("%d items got.", count($result['Responses'][$this->tableName]));
                    foreach ($result['Responses'][$this->tableName] as $item) {
                        $item = DynamoDbItem::createFromTypedArray($item);
                        $returnSet[] = $item->toArray();
                    }
                }
                //mdebug("Consumed = %.1f", $result['ConsumedCapacity'][0]['CapacityUnits']);
            },
            onRejected: static function ($e) {
                throw $e;
            }
        )->wait();

        if ($unprocessed) {
            $retryDelay = $retryDelay ?: 925;
            usleep(microseconds: $retryDelay * 1000);
            $nextRetry = $retryDelay * 1.2;

            if ($nextRetry > $maxDelay) {
                $nextRetry = $maxDelay;
            }

            $returnSet = array_merge(
                $returnSet,
                $this->batchGet(
                    keys: $unprocessed,
                    isConsistentRead: $isConsistentRead,
                    concurrency: $concurrency,
                    projectedFields: $projectedFields,
                    keyIsTyped: true,
                    retryDelay: $nextRetry
                )
            );
        }

        return $returnSet;
    }

    public function batchPut(
        array $objs,
        $concurrency = 10,
        $maxDelay = 15000
    ): void {
        $this->doBatchWrite(
            isPut: true,
            objs: $objs,
            concurrency: $concurrency,
            maxDelay: $maxDelay
        );
    }

    public function delete($keys): void
    {
        $keyItem = DynamoDbItem::createFromArray($keys, $this->attributeTypes);

        $requestArgs = [
            "TableName" => $this->tableName,
            "Key"       => $keyItem->getData(),
        ];

        $this->dbClient->deleteItem($requestArgs);
    }

    public function deleteGlobalSecondaryIndex($indexName): void
    {
        if (!$this->getGlobalSecondaryIndices(
            namePattern: sprintf("/%s/", preg_quote(str: $indexName, delimiter: "/"))
        )) {
            throw new RuntimeException(message: "Global Secondary Index doesn't exist, name = $indexName");
        }

        $args = [
            'GlobalSecondaryIndexUpdates' => [
                [
                    'Delete' => [
                        'IndexName' => $indexName,
                    ],
                ],
            ],
            'TableName'                   => $this->tableName,
        ];
        $this->dbClient->updateTable($args);
    }

    public function describe()
    {
        $requestArgs = [
            "TableName" => $this->tableName,
        ];

        if (isset(self::$describeCache[$this->tableName])) {
            return self::$describeCache[$this->tableName]['Table'];
        }

        $result = $this->dbClient->describeTable($requestArgs);
        self::$describeCache[$this->tableName] = $result;

        return $result['Table'];
    }

    public function describeTimeToLive()
    {
        $requestArgs = [
            "TableName" => $this->tableName,
        ];

        if (isset(self::$describeTTLCache[$this->tableName])) {
            return self::$describeTTLCache[$this->tableName]['TimeToLiveDescription'];
        }

        $result = $this->dbClient->describeTimeToLive($requestArgs);
        self::$describeTTLCache[$this->tableName] = $result;

        return $result['TimeToLiveDescription'];
    }

    public function disableStream(): void
    {
        $args = [
            "TableName"           => $this->tableName,
            "StreamSpecification" => [
                'StreamEnabled' => false,
            ],
        ];
        $this->dbClient->updateTable($args);
    }

    public function enableStream($type = "NEW_AND_OLD_IMAGES"): void
    {
        $args = [
            "TableName"           => $this->tableName,
            "StreamSpecification" => [
                'StreamEnabled'  => true,
                'StreamViewType' => $type,
            ],
        ];
        $this->dbClient->updateTable($args);
    }

    public function get(array $keys, $is_consistent_read = false, $projectedFields = []): ?array
    {
        $keyItem = DynamoDbItem::createFromArray($keys, $this->attributeTypes);
        $requestArgs = [
            "TableName" => $this->tableName,
            "Key"       => $keyItem->getData(),
        ];

        if ($projectedFields) {
            $fieldsMapping = [];

            foreach ($projectedFields as $idx => $field) {
                $projectedFields[$idx] = $escaped = '#'.$field;
                $fieldsMapping[$escaped] = $field;
            }

            $requestArgs['ProjectionExpression'] = implode(', ', $projectedFields);
            $requestArgs['ExpressionAttributeNames'] = $fieldsMapping;
        }

        if ($is_consistent_read) {
            $requestArgs["ConsistentRead"] = true;
        }

        $result = $this->dbClient->getItem($requestArgs);

        if ($result['Item']) {
            return DynamoDbItem::createFromTypedArray((array) $result['Item'])->toArray();
        }

        return null;
    }

    public function getConsumedCapacity(
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $period = 60,
        $num_of_period = 5,
        $timeshift = -300
    ): array {
        $cloudwatch = new CloudWatchClient(
            args: [
                "profile" => $this->config['profile'],
                "region"  => $this->config['region'],
                "version" => "2010-08-01",
            ]
        );

        $end = time() + $timeshift;
        $end -= $end % $period;
        $start = $end - $num_of_period * $period;

        $requestArgs = [
            "Namespace"  => "AWS/DynamoDB",
            "Dimensions" => [
                [
                    "Name"  => "TableName",
                    "Value" => $this->tableName,
                ],
            ],
            "MetricName" => "ConsumedReadCapacityUnits",
            "StartTime"  => date(format: 'c', timestamp: $start),
            "EndTime"    => date(format: 'c', timestamp: $end),
            "Period"     => 60,
            "Statistics" => ["Sum"],
        ];

        if ($indexName !== DynamoDbIndex::PRIMARY_INDEX) {
            $requestArgs['Dimensions'][] = [
                "Name"  => "GlobalSecondaryIndexName",
                "Value" => $indexName,
            ];
        }

        $result = $cloudwatch->getMetricStatistics($requestArgs);
        $total_read = 0;
        $total_count = 0;

        foreach ($result['Datapoints'] as $data) {
            $total_count++;
            $total_read += $data['Sum'];
        }

        $readUsed = $total_count ? ($total_read / $total_count / 60) : 0;

        $requestArgs['MetricName'] = 'ConsumedWriteCapacityUnits';
        $result = $cloudwatch->getMetricStatistics($requestArgs);
        $total_write = 0;
        $total_count = 0;

        foreach ($result['Datapoints'] as $data) {
            $total_count++;
            $total_write += $data['Sum'];
        }

        $writeUsed = $total_count ? ($total_write / $total_count / 60) : 0;

        return [
            $readUsed,
            $writeUsed,
        ];
    }

    public function getDbClient(): DynamoDbClient
    {
        return $this->dbClient;
    }

    public function getGlobalSecondaryIndices(string $namePattern = "/.*/"): array
    {
        $description = $this->describe();
        $gsiDefs = $description['GlobalSecondaryIndexes'] ?? null;

        if (!$gsiDefs) {
            return [];
        }

        $attrDefs = [];

        foreach ($description['AttributeDefinitions'] as $attributeDefinition) {
            $attrDefs[$attributeDefinition['AttributeName']] = $attributeDefinition['AttributeType'];
        }

        $gsis = [];
        foreach ($gsiDefs as $gsiDef) {
            $indexName = $gsiDef['IndexName'];

            if (!preg_match(pattern: $namePattern, subject: $indexName)) {
                continue;
            }

            $hashKey = null;
            $hashKeyType = null;
            $rangeKey = null;
            $rangeKeyType = null;

            foreach ($gsiDef['KeySchema'] as $keySchema) {
                switch ($keySchema['KeyType']) {
                    case "HASH":
                        $hashKey = $keySchema['AttributeName'];
                        $hashKeyType = $attrDefs[$hashKey];
                        break;
                    case "RANGE":
                        $rangeKey = $keySchema['AttributeName'];
                        $rangeKeyType = $attrDefs[$rangeKey];
                        break;
                }
            }

            $projectionType = $gsiDef['Projection']['ProjectionType'];
            $projectedAttributes = $gsiDef['Projection']['NonKeyAttributes'] ?? [];
            $gsi = new DynamoDbIndex(
                hashKey: $hashKey,
                hashKeyType: $hashKeyType,
                rangeKey: $rangeKey,
                rangeKeyType: $rangeKeyType,
                projectionType: $projectionType,
                projectedAttributes: $projectedAttributes
            );
            $gsi->setName(name: $indexName);
            $gsis[$indexName] = $gsi;
        }

        return $gsis;
    }

    public function getLocalSecondaryIndices(): array
    {
        $description = $this->describe();
        $lsiDefs = $description['LocalSecondaryIndexes'] ?? null;

        if (!$lsiDefs) {
            return [];
        }

        $attrDefs = [];

        foreach ($description['AttributeDefinitions'] as $attributeDefinition) {
            $attrDefs[$attributeDefinition['AttributeName']] = $attributeDefinition['AttributeType'];
        }

        $lsis = [];
        foreach ($lsiDefs as $lsiDef) {
            $hashKey = null;
            $hashKeyType = null;
            $rangeKey = null;
            $rangeKeyType = null;

            foreach ($lsiDef['KeySchema'] as $keySchema) {
                switch ($keySchema['KeyType']) {
                    case "HASH":
                        $hashKey = $keySchema['AttributeName'];
                        $hashKeyType = $attrDefs[$hashKey];
                        break;
                    case "RANGE":
                        $rangeKey = $keySchema['AttributeName'];
                        $rangeKeyType = $attrDefs[$rangeKey];
                        break;
                }
            }

            $projectionType = $lsiDef['Projection']['ProjectionType'];
            $projectedAttributes = $lsiDef['Projection']['NonKeyAttributes'] ?? [];
            $lsi = new DynamoDbIndex(
                hashKey: $hashKey,
                hashKeyType: $hashKeyType,
                rangeKey: $rangeKey,
                rangeKeyType: $rangeKeyType,
                projectionType: $projectionType,
                projectedAttributes: $projectedAttributes
            );
            $lsi->setName(name: $lsiDef['IndexName']);
            $lsis[$lsi->getName()] = $lsi;
        }

        return $lsis;
    }

    public function getPrimaryIndex(): DynamoDbIndex
    {
        $description = $this->describe();
        $attrDefs = [];

        foreach ($description['AttributeDefinitions'] as $attributeDefinition) {
            $attrDefs[$attributeDefinition['AttributeName']] = $attributeDefinition['AttributeType'];
        }

        $hashKey = null;
        $hashKeyType = null;
        $rangeKey = null;
        $rangeKeyType = null;
        $keySchemas = $description['KeySchema'];

        foreach ($keySchemas as $keySchema) {
            switch ($keySchema['KeyType']) {
                case "HASH":
                    $hashKey = $keySchema['AttributeName'];
                    $hashKeyType = $attrDefs[$hashKey];
                    break;
                case "RANGE":
                    $rangeKey = $keySchema['AttributeName'];
                    $rangeKeyType = $attrDefs[$rangeKey];
                    break;
            }
        }

        return new DynamoDbIndex(
            hashKey: $hashKey,
            hashKeyType: $hashKeyType,
            rangeKey: $rangeKey,
            rangeKeyType: $rangeKeyType
        );
    }

    public function getTableName(): mixed
    {
        return $this->tableName;
    }

    public function getThroughput($indexName = DynamoDbIndex::PRIMARY_INDEX): array
    {
        $result = $this->describe();
        if ($indexName === DynamoDbIndex::PRIMARY_INDEX) {
            return [
                $result['Table']['ProvisionedThroughput']['ReadCapacityUnits'],
                $result['Table']['ProvisionedThroughput']['WriteCapacityUnits'],
            ];
        }

        foreach ($result['Table']['GlobalSecondaryIndexes'] as $gsi) {
            if ($gsi['IndexName'] !== $indexName) {
                continue;
            }

            return [
                $gsi['ProvisionedThroughput']['ReadCapacityUnits'],
                $gsi['ProvisionedThroughput']['WriteCapacityUnits'],
            ];
        }

        throw new UnexpectedValueException(message: "Cannot find index named $indexName");
    }

    public function isStreamEnabled(&$streamViewType = null)
    {
        $streamViewType = null;
        $description = $this->describe();

        if (!isset($description['StreamSpecification'])) {
            return false;
        }

        $isEnabled = $description['StreamSpecification']['StreamEnabled'];
        $streamViewType = $description['StreamSpecification']['StreamViewType'];

        return $isEnabled;
    }

    public function multiQueryAndRun(
        callable $callback,
        $hashKeyName,
        $hashKeyValues,
        $rangeKeyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $concurrency = 10,
        $projectedFields = []
    ): void {
        $wrapper = new MultiQueryCommandWrapper();
        $wrapper(
            dbClient: $this->dbClient,
            tableName: $this->tableName,
            callback: $callback,
            hashKeyName: $hashKeyName,
            hashKeyValues: $hashKeyValues,
            rangeKeyConditions: $rangeKeyConditions,
            fieldsMapping: $fieldsMapping,
            paramsMapping: $paramsMapping,
            indexName: $indexName,
            filterExpression: $filterExpression,
            evaluationLimit: $evaluationLimit,
            isConsistentRead: $isConsistentRead,
            isAscendingOrder: $isAscendingOrder,
            concurrency: $concurrency,
            projectedFields: $projectedFields
        );
    }

    public function parallelScanAndRun(
        $parallel,
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ): void {
        $wrapper = new ParallelScanCommandWrapper();

        $wrapper(
            dbClient: $this->dbClient,
            tableName: $this->tableName,
            callback: $callback,
            filterExpression: $filterExpression,
            fieldsMapping: $fieldsMapping,
            paramsMapping: $paramsMapping,
            indexName: $indexName,
            evaluationLimit: 1000,
            isConsistentRead: $isConsistentRead,
            isAscendingOrder: $isAscendingOrder,
            totalSegments: $parallel,
            countOnly: false,
            projectedAttributes: $projectedFields
        );
    }

    public function query(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ): array {
        $wrapper = new QueryCommandWrapper();

        $ret = [];
        $wrapper(
            dbClient: $this->dbClient,
            tableName: $this->tableName,
            callback: function ($item) use (&$ret) {
                $ret[] = $item;
            },
            keyConditions: $keyConditions,
            fieldsMapping: $fieldsMapping,
            paramsMapping: $paramsMapping,
            indexName: $indexName,
            filterExpression: $filterExpression,
            lastKey: $lastKey,
            evaluationLimit: $evaluationLimit,
            isConsistentRead: $isConsistentRead,
            isAscendingOrder: $isAscendingOrder,
            countOnly: false,
            projectedFields: $projectedFields
        );

        return $ret;
    }

    public function queryAndRun(
        callable $callback,
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ): void {
        $lastKey = null;
        $stoppedByCallback = false;
        $wrapper = new QueryCommandWrapper();

        do {
            $wrapper(
                dbClient: $this->dbClient,
                tableName: $this->tableName,
                callback: function ($item) use (&$stoppedByCallback, $callback) {
                    if ($stoppedByCallback) {
                        return;
                    }

                    $ret = $callback($item);
                    if ($ret === false) {
                        $stoppedByCallback = true;
                    }
                },
                keyConditions: $keyConditions,
                fieldsMapping: $fieldsMapping,
                paramsMapping: $paramsMapping,
                indexName: $indexName,
                filterExpression: $filterExpression,
                lastKey: $lastKey,
                evaluationLimit: 1000,
                isConsistentRead: $isConsistentRead,
                isAscendingOrder: $isAscendingOrder,
                countOnly: false,
                projectedFields: $projectedFields
            );
        } while ($lastKey !== null && !$stoppedByCallback);
    }

    public function queryCount(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    ): int|bool|array {
        $ret = 0;
        $lastKey = null;
        $wrapper = new QueryCommandWrapper();

        do {
            $ret += $wrapper(
                dbClient: $this->dbClient,
                tableName: $this->tableName,
                callback: static function () {
                },
                keyConditions: $keyConditions,
                fieldsMapping: $fieldsMapping,
                paramsMapping: $paramsMapping,
                indexName: $indexName,
                filterExpression: $filterExpression,
                lastKey: $lastKey,
                evaluationLimit: 10000,
                // max size of a query is 1MB of data, a limit of 10k for items with a typical size of 100B
                isConsistentRead: $isConsistentRead,
                isAscendingOrder: $isAscendingOrder,
                countOnly: true,
                projectedFields: []
            );
        } while ($lastKey !== null);

        return $ret;
    }

    public function scan(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ): array {
        $wrapper = new ScanCommandWrapper();

        $ret = [];
        $wrapper(
            dbClient: $this->dbClient,
            tableName: $this->tableName,
            callback: function ($item) use (&$ret) {
                $ret[] = $item;
            },
            filterExpression: $filterExpression,
            fieldsMapping: $fieldsMapping,
            paramsMapping: $paramsMapping,
            indexName: $indexName,
            lastKey: $lastKey,
            evaluationLimit: $evaluationLimit,
            isConsistentRead: $isConsistentRead,
            isAscendingOrder: $isAscendingOrder,
            countOnly: false,
            projectedAttributes: $projectedFields
        );

        return $ret;
    }

    public function scanAndRun(
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ): void {
        $lastKey = null;
        $stoppedByCallback = false;
        $wrapper = new ScanCommandWrapper();

        do {
            $wrapper(
                dbClient: $this->dbClient,
                tableName: $this->tableName,
                callback: function ($item) use (&$stoppedByCallback, $callback) {
                    if ($stoppedByCallback) {
                        return;
                    }

                    $ret = $callback($item);
                    if ($ret === false) {
                        $stoppedByCallback = true;
                    }
                },
                filterExpression: $filterExpression,
                fieldsMapping: $fieldsMapping,
                paramsMapping: $paramsMapping,
                indexName: $indexName,
                lastKey: $lastKey,
                evaluationLimit: 1000,
                isConsistentRead: $isConsistentRead,
                isAscendingOrder: $isAscendingOrder,
                countOnly: false,
                projectedAttributes: $projectedFields
            );
        } while ($lastKey !== null && !$stoppedByCallback);
    }

    public function scanCount(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $isConsistentRead = false,
        $parallel = 10
    ): int {
        $wrapper = new ParallelScanCommandWrapper();

        return $wrapper(
            dbClient: $this->dbClient,
            tableName: $this->tableName,
            callback: static function () {
            },
            filterExpression: $filterExpression,
            fieldsMapping: $fieldsMapping,
            paramsMapping: $paramsMapping,
            indexName: $indexName,
            evaluationLimit: 10000,
            // max size of a query is 1MB of data, a limit of 10k for items with a typical size of 100B
            isConsistentRead: $isConsistentRead,
            isAscendingOrder: true,
            totalSegments: $parallel,
            countOnly: true,
            projectedAttributes: []
        );
    }

    public function set(array $obj, $checkValues = []): bool
    {
        $requestArgs = [
            "TableName" => $this->tableName,
        ];

        if ($checkValues) {
            $conditionExpressions = [];
            $expressionAttributeNames = [];
            $expressionAttributeValues = [];

            $typedCheckValues = DynamoDbItem::createFromArray($checkValues)->getData();
            $casCounter = 0;
            foreach ($typedCheckValues as $field => $checkValue) {
                $casCounter++;
                $fieldPlaceholder = "#field$casCounter";
                $valuePlaceholder = ":val$casCounter";

                if (isset($checkValue['NULL'])) {
                    $conditionExpressions[] = "(attribute_not_exists($fieldPlaceholder) OR $fieldPlaceholder = $valuePlaceholder)";
                } else {
                    $conditionExpressions[] = "$fieldPlaceholder = $valuePlaceholder";
                }

                $expressionAttributeNames[$fieldPlaceholder] = $field;
                $expressionAttributeValues[$valuePlaceholder] = $checkValue;
            }

            $requestArgs['ConditionExpression'] = implode(separator: " AND ", array: $conditionExpressions);
            $requestArgs['ExpressionAttributeNames'] = $expressionAttributeNames;
            $requestArgs['ExpressionAttributeValues'] = $expressionAttributeValues;
        }
        $item = DynamoDbItem::createFromArray($obj, $this->attributeTypes);
        $requestArgs['Item'] = $item->getData();

        try {
            $this->dbClient->putItem($requestArgs);
        } catch (DynamoDbException $e) {
            if ($e->getAwsErrorCode() === "ConditionalCheckFailedException") {
                return false;
            }

            throw $e;
        }

        return true;
    }

    public function setAttributeType($name, $type): self
    {
        $this->attributeTypes[$name] = $type;

        return $this;
    }

    public function setThroughput($read, $write, $indexName = DynamoDbIndex::PRIMARY_INDEX): void
    {
        $requestArgs = [
            "TableName" => $this->tableName,
        ];
        $updateObject = [
            'ReadCapacityUnits'  => $read,
            'WriteCapacityUnits' => $write,
        ];

        if ($indexName === DynamoDbIndex::PRIMARY_INDEX) {
            $requestArgs['ProvisionedThroughput'] = $updateObject;
        } else {
            $requestArgs['GlobalSecondaryIndexUpdates'] = [
                [
                    'Update' => [
                        'IndexName'             => $indexName,
                        'ProvisionedThroughput' => $updateObject,
                    ],
                ],
            ];
        }

        try {
            $this->dbClient->updateTable($requestArgs);
        } catch (DynamoDbException $e) {
            throw $e;
        }
    }

    protected function doBatchWrite(
        $isPut,
        array $objs,
        $concurrency = 10,
        $objIsTyped = false,
        $retryDelay = 0,
        $maxDelay = 15000
    ): void {
        $promises = [];
        $writes = [];
        $unprocessed = [];

        $flushCallback = function ($limit = 25) use (&$promises, &$writes) {
            if (count($writes) >= $limit) {
                $reqArgs = [
                    "RequestItems" => [
                        $this->tableName => $writes,
                    ],
                ];
                $promise = $this->dbClient->batchWriteItemAsync($reqArgs);
                $promises[] = $promise;
                $writes = [];
            }
        };

        foreach ($objs as $obj) {
            $item = $objIsTyped
                ? DynamoDbItem::createFromTypedArray($obj)
                : DynamoDbItem::createFromArray($obj, $this->attributeTypes);

            if ($isPut) {
                $req = [
                    "PutRequest" => [
                        "Item" => $item->getData(),
                    ],
                ];
            } else {
                $req = [
                    "DeleteRequest" => [
                        "Key" => $item->getData(),
                    ],
                ];
            }

            $writes[] = $req;
            $flushCallback();
        }
        $flushCallback(1);

        Each::ofLimit(
            iterable: $promises,
            concurrency: $concurrency,
            onFulfilled: function (Result $result) use ($isPut, &$unprocessed) {
                $unprocessedItems = $result['UnprocessedItems'];
                if (isset($unprocessedItems[$this->tableName])) {
                    $currentUnprocessed = $unprocessedItems[$this->tableName];
                    foreach ($currentUnprocessed as $action) {
                        if ($isPut) {
                            $unprocessed[] = $action['PutRequest']['Item'];
                        } else {
                            $unprocessed[] = $action['DeleteRequest']['Key'];
                        }
                    }
                }
            },
            onRejected: static function ($e) {
                throw $e;
            }
        )->wait();

        if ($unprocessed) {
            $retryDelay = $retryDelay ?: 925;
            $nextRetry = $retryDelay * 1.2;
            if ($nextRetry > $maxDelay) {
                $nextRetry = $maxDelay;
            }
            usleep(microseconds: $retryDelay * 1000);
            $this->doBatchWrite(
                isPut: $isPut,
                objs: $unprocessed,
                concurrency: $concurrency,
                objIsTyped: true,
                retryDelay: $nextRetry
            );
        }
    }
}

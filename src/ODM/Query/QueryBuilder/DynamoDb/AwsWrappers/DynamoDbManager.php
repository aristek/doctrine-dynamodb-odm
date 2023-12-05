<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use Exception;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\Utils;
use function array_merge;
use function array_search;
use function array_splice;
use function array_values;
use function ceil;
use function is_string;
use function preg_match;
use function sleep;
use function time;

final class DynamoDbManager
{
    public const PAY_PER_REQUEST = 'PAY_PER_REQUEST';
    public const PROVISIONED = 'PROVISIONED';

    protected array $config = [];

    protected DynamoDbClient $db;

    public function __construct(array $awsConfig)
    {
        $this->db = new DynamoDbClient($awsConfig);
    }

    /**
     * @param DynamoDbIndex[] $localSecondaryIndices
     * @param DynamoDbIndex[] $globalSecondaryIndices
     *
     * @internal param DynamoDbIndex $primaryKey
     */
    public function createTable(
        string $tableName,
        DynamoDbIndex $primaryIndex,
        array $localSecondaryIndices = [],
        array $globalSecondaryIndices = [],
        array $tags = []
    ): bool {
        $attrDef = $primaryIndex->getAttributeDefinitions();

        foreach ($globalSecondaryIndices as $gsi) {
            $gsiDef = $gsi->getAttributeDefinitions();
            $attrDef = array_merge($attrDef, $gsiDef);
        }

        foreach ($localSecondaryIndices as $lsi) {
            $lsiDef = $lsi->getAttributeDefinitions();
            $attrDef = array_merge($attrDef, $lsiDef);
        }

        $attrDef = array_values($attrDef);

        $keySchema = $primaryIndex->getKeySchema();

        $gsiDef = [];
        foreach ($globalSecondaryIndices as $globalSecondaryIndex) {
            $gsiDef[] = [
                "IndexName"  => $globalSecondaryIndex->getName(),
                "KeySchema"  => $globalSecondaryIndex->getKeySchema(),
                "Projection" => $globalSecondaryIndex->getProjection(),
            ];
        }

        $lsiDef = [];
        foreach ($localSecondaryIndices as $localSecondaryIndex) {
            $lsiDef[] = [
                "IndexName"  => $localSecondaryIndex->getName(),
                "KeySchema"  => $localSecondaryIndex->getKeySchema(),
                "Projection" => $localSecondaryIndex->getProjection(),
            ];
        }

        $args = [
            "TableName"            => $tableName,
            "AttributeDefinitions" => $attrDef,
            "KeySchema"            => $keySchema,
            "BillingMode"          => self::PAY_PER_REQUEST,
        ];

        if (!empty($tags)) {
            $tagsDef = [];

            foreach ($tags as $key => $value) {
                $tagsDef[] = [
                    "Key"   => $key,
                    "Value" => $value,
                ];
            }

            $args["Tags"] = $tagsDef;
        }

        if ($gsiDef) {
            $args["GlobalSecondaryIndexes"] = $gsiDef;
        }

        if ($lsiDef) {
            $args["LocalSecondaryIndexes"] = $lsiDef;
        }

        $result = $this->db->createTable($args);

        return isset($result["TableDescription"]) && $result["TableDescription"];
    }

    public function deleteTable(string $tableName): bool
    {
        $args = [
            "TableName" => $tableName,
        ];
        $result = $this->db->deleteTable($args);

        return isset($result["TableDescription"]) && $result["TableDescription"];
    }

    /**
     * @throws Exception
     */
    public function listTables(string $pattern = '/.*/'): array
    {
        $tables = [];
        $lastEvaluatedTableName = null;
        do {
            $args = [
                "Limit" => 30,
            ];

            if ($lastEvaluatedTableName) {
                $args["ExclusiveStartTableName"] = $lastEvaluatedTableName;
            }

            $cmd = $this->db->getCommand(
                "ListTables",
                $args
            );
            $result = $this->db->execute($cmd);

            $lastEvaluatedTableName = $result["LastEvaluatedTableName"] ?? null;

            foreach ($result["TableNames"] as $tableName) {
                if (preg_match($pattern, $tableName)) {
                    $tables[] = $tableName;
                }
            }
        } while ($lastEvaluatedTableName !== null);

        return $tables;
    }

    public function updateTimeToLive(string $tableName, string $ttlAttribute): bool
    {
        $result = $this->db->updateTimeToLive([
            "TableName"               => $tableName,
            "TimeToLiveSpecification" => [
                "AttributeName" => $ttlAttribute,
                "Enabled"       => true,
            ],
        ]);

        return isset($result["TimeToLiveSpecification"]) && $result["TimeToLiveSpecification"];
    }

    public function waitForTableCreation(
        string $tableName,
        int $timeout = 60,
        int $pollInterval = 1,
        bool $blocking = true
    ): Coroutine|bool {
        $args = [
            "TableName" => $tableName,
            "@waiter"   => [
                "delay"       => $pollInterval,
                "maxAttempts" => ceil($timeout / $pollInterval),
            ],
        ];

        $promise = $this->db->getWaiter("TableExists", $args)->promise();

        if ($blocking) {
            $promise->wait();

            return true;
        }

        return $promise;
    }

    public function waitForTableDeletion(
        string $tableName,
        int $timeout = 60,
        int $pollInterval = 1,
        bool $blocking = true
    ): Coroutine|bool {
        $args = [
            "TableName" => $tableName,
            "@waiter"   => [
                "delay"       => $pollInterval,
                "maxAttempts" => ceil($timeout / $pollInterval),
            ],
        ];

        $promise = $this->db->getWaiter("TableNotExists", $args)->promise();

        if ($blocking) {
            $promise->wait();

            return true;
        }

        return $promise;
    }

    public function waitForTablesToBeFullyReady(string|array $tableNames, int $timeout = 60, int $interval = 2): bool
    {
        $started = time();
        if (is_string($tableNames)) {
            $tableNames = [$tableNames];
        }

        while ($tableNames) {
            $promises = [];

            foreach ($tableNames as $tableName) {
                $args = [
                    "TableName" => $tableName,
                ];
                $promise = $this->db->describeTableAsync($args);
                $promise->then(
                    function (Result $result) use (&$tableNames, $tableName) {
                        if ($result["Table"]["TableStatus"] === "ACTIVE") {
                            if (isset($result["Table"]["GlobalSecondaryIndexes"])
                                && $result["Table"]["GlobalSecondaryIndexes"]
                            ) {
                                foreach ($result["Table"]["GlobalSecondaryIndexes"] as $gsi) {
                                    if ($gsi["IndexStatus"] !== "ACTIVE") {
                                        return;
                                    }
                                }
                            }

                            $k = array_search($tableName, $tableNames, true);
                            array_splice($tableNames, $k, 1);
                        }
                    }
                );

                $promises[] = $promise;
            }

            Utils::all($promises)->wait();

            if ($tableNames) {
                if (time() - $started > $timeout) {
                    return false;
                }

                sleep($interval);
            }
        }

        return true;
    }
}

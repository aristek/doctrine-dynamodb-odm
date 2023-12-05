<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use GuzzleHttp\Promise\Promise;

/**
 * @method Result batchGetItem()
 * @method Promise batchGetItemAsync()
 * @method Result batchWriteItem()
 * @method Promise batchWriteItemAsync()
 * @method Result createTable()
 * @method Promise createTableAsync()
 * @method Result deleteItem()
 * @method Promise deleteItemAsync()
 * @method Result deleteTable()
 * @method Promise deleteTableAsync()
 * @method Result describeTable()
 * @method Promise describeTableAsync()
 * @method Result getItem()
 * @method Promise getItemAsync()
 * @method Result listTables()
 * @method Promise listTablesAsync()
 * @method Result putItem()
 * @method Promise putItemAsync()
 * @method Result query()
 * @method Promise queryAsync()
 * @method Result scan()
 * @method Promise scanAsync()
 * @method Result updateItem()
 * @method Promise updateItemAsync()
 * @method Result updateTable()
 * @method Promise updateTableAsync()
 * @method Result createBackup() (supported in versions 2012-08-10)
 * @method Promise createBackupAsync() (supported in versions 2012-08-10)
 * @method Result createGlobalTable() (supported in versions 2012-08-10)
 * @method Promise createGlobalTableAsync() (supported in versions 2012-08-10)
 * @method Result deleteBackup() (supported in versions 2012-08-10)
 * @method Promise deleteBackupAsync() (supported in versions 2012-08-10)
 * @method Result describeBackup() (supported in versions 2012-08-10)
 * @method Promise describeBackupAsync() (supported in versions 2012-08-10)
 * @method Result describeContinuousBackups() (supported in versions 2012-08-10)
 * @method Promise describeContinuousBackupsAsync() (supported in versions 2012-08-10)
 * @method Result describeGlobalTable() (supported in versions 2012-08-10)
 * @method Promise describeGlobalTableAsync() (supported in versions 2012-08-10)
 * @method Result describeLimits() (supported in versions 2012-08-10)
 * @method Promise describeLimitsAsync() (supported in versions 2012-08-10)
 * @method Result describeTimeToLive() (supported in versions 2012-08-10)
 * @method Promise describeTimeToLiveAsync() (supported in versions 2012-08-10)
 * @method Result listBackups() (supported in versions 2012-08-10)
 * @method Promise listBackupsAsync() (supported in versions 2012-08-10)
 * @method Result listGlobalTables() (supported in versions 2012-08-10)
 * @method Promise listGlobalTablesAsync() (supported in versions 2012-08-10)
 * @method Result listTagsOfResource() (supported in versions 2012-08-10)
 * @method Promise listTagsOfResourceAsync() (supported in versions 2012-08-10)
 * @method Result restoreTableFromBackup() (supported in versions 2012-08-10)
 * @method Promise restoreTableFromBackupAsync() (supported in versions 2012-08-10)
 * @method Result tagResource() (supported in versions 2012-08-10)
 * @method Promise tagResourceAsync() (supported in versions 2012-08-10)
 * @method Result untagResource() (supported in versions 2012-08-10)
 * @method Promise untagResourceAsync() (supported in versions 2012-08-10)
 * @method Result updateGlobalTable() (supported in versions 2012-08-10)
 * @method Promise updateGlobalTableAsync() (supported in versions 2012-08-10)
 * @method Result updateTimeToLive() (supported in versions 2012-08-10)
 * @method Promise updateTimeToLiveAsync() (supported in versions 2012-08-10)
 * @method Result transactGetItems(array $args = []) (supported in versions 2012-08-10)
 * @method Promise transactGetItemsAsync(array $args = []) (supported in versions 2012-08-10)
 * @method Result transactWriteItems(array $args = []) (supported in versions 2012-08-10)
 * @method Promise transactWriteItemsAsync(array $args = []) (supported in versions 2012-08-10)
 */
final class ExecutableQuery
{
    public array $query;

    private DynamoDbClient $client;

    public function __construct(DynamoDbClient $client, array $query)
    {
        $this->client = $client;
        $this->query = $query;
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->client->{$method}($this->query);
    }
}

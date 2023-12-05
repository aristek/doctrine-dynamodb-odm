<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

final class DynamoDbClientService implements DynamoDbClientInterface
{
    public function __construct(
        private readonly DynamoDbClient $client,
        private readonly Marshaler $marshaler,
        private readonly EmptyAttributeFilter $attributeFilter
    ) {
    }

    public function getAttributeFilter(): EmptyAttributeFilter
    {
        return $this->attributeFilter;
    }

    public function getClient(): DynamoDbClient
    {
        return $this->client;
    }

    public function getMarshaler(): Marshaler
    {
        return $this->marshaler;
    }
}

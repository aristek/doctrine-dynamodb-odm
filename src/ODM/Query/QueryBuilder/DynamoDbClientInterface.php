<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;

interface DynamoDbClientInterface
{
    public function getAttributeFilter();

    public function getClient();

    public function getMarshaler();
}

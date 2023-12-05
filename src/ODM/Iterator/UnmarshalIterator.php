<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Iterator;

use IteratorIterator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\DynamoDbManager;
use Traversable;
use function array_map;
use function iterator_to_array;

final class UnmarshalIterator extends IteratorIterator implements Iterator
{
    public function __construct(
        private readonly DynamoDbManager $dynamoDbManager,
        Traversable $iterator,
    ) {
        parent::__construct($iterator);
    }

    public function current(): mixed
    {
        return $this->dynamoDbManager->unmarshalItem(parent::current());
    }

    public function toArray(): array
    {
        $dynamoDbManager = $this->dynamoDbManager;

        return array_map(
            static fn(array $item): array => $dynamoDbManager->unmarshalItem($item),
            iterator_to_array($this->getInnerIterator())
        );
    }
}

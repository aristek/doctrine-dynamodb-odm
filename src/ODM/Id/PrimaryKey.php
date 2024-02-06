<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Id;

class PrimaryKey
{
    public const HASH = 'pk';
    public const RANGE = 'sk';

    public function __construct(
        public readonly mixed $hash,
        public readonly mixed $range = null,
        public readonly ?string $name = null,
    ) {
    }

    public function getHash(): mixed
    {
        return $this->hash;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRange(): mixed
    {
        return $this->range;
    }
}

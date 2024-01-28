<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Id;

class Index
{
    public const HASH = 'pk';
    public const RANGE = 'sk';

    public function __construct(
        public readonly string|int $hash,
        public readonly string|int|null $range = null,
        public readonly ?string $name = null,
    ) {
    }

    public function getHash(): string
    {
        return (string) $this->hash;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getRange(): ?string
    {
        return (string) $this->range;
    }
}

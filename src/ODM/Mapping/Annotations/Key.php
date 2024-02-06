<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

abstract class Key
{
    public function __construct(
        public readonly string $field,
        public readonly string $key,
        public readonly Strategy $strategy
    ) {
    }
}

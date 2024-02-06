<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

final class HashKey extends Key
{
    public function __construct(
        string $key,
        ?string $field = null,
        ?string $strategy = null
    ) {
        parent::__construct(
            key: $key,
            field: $field,
            strategy: $strategy ? new Strategy(mask: $strategy) : null
        );
    }
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

final class RangeKey extends Key
{
    public function __construct(
        string $field,
        ?string $key = null,
        ?string $strategy = null
    ) {
        parent::__construct(
            field: $field,
            key: $key ?: $field,
            strategy: new Strategy(
                mask: $strategy ?: Strategy::SK_STRATEGY_FORMAT
            )
        );
    }
}

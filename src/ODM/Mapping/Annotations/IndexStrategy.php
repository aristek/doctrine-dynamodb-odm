<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

final class IndexStrategy
{
    public function __construct(
        public readonly Strategy $hash,
        public readonly Strategy $range
    ) {
    }
}

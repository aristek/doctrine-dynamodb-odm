<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

abstract class AbstractField implements Annotation
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly bool $nullable = false,
        public readonly array $options = [],
    ) {
    }
}

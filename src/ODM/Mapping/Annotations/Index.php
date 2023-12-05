<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

class Index
{
    public const TYPE_ALL = 'ALL';
    public const TYPE_INCLUDE = 'INCLUDE';
    public const TYPE_KEYS_ONLY = 'KEYS_ONLY';

    /**
     * @Enum({"ALL", "INCLUDE", "KEYS_ONLY"})
     */
    public string $projectionType = 'ALL';

    public function __construct(
        public readonly string $hash,
        public readonly string $name,
        public readonly IndexStrategy $strategy,
        public readonly array $projectedAttributes = [],
        string $projectionType = self::TYPE_ALL,
        public readonly ?string $range = null,

    ) {
        $this->projectionType = $projectionType;
    }
}

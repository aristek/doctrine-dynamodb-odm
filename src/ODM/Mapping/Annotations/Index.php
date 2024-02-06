<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;

class Index extends PrimaryKey
{
    public const TYPE_ALL = 'ALL';
    public const TYPE_INCLUDE = 'INCLUDE';
    public const TYPE_KEYS_ONLY = 'KEYS_ONLY';

    /**
     * @Enum({"ALL", "INCLUDE", "KEYS_ONLY"})
     */
    public string $projectionType = 'ALL';

    public function __construct(
        public readonly Key $hashKey,
        string $name,
        public readonly array $projectedAttributes = [],
        string $projectionType = self::TYPE_ALL,
        public readonly ?Key $rangeKey = null,

    ) {
        $this->projectionType = $projectionType;

        parent::__construct($this->hashKey->key, $this->rangeKey?->key, $name);
    }
}

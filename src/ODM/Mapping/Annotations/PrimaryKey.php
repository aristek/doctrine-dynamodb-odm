<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey as IdIndex;

class PrimaryKey extends IdIndex
{
    public const TYPE_ALL = 'ALL';
    public const TYPE_INCLUDE = 'INCLUDE';
    public const TYPE_KEYS_ONLY = 'KEYS_ONLY';

    /**
     * @Enum({"ALL", "INCLUDE", "KEYS_ONLY"})
     */
    public string $projectionType = 'ALL';

    public function __construct(
        string $hash,
        string $name,
        public readonly IndexStrategy $strategy,
        public readonly array $projectedAttributes = [],
        string $projectionType = self::TYPE_ALL,
        ?string $range = null,

    ) {
        $this->projectionType = $projectionType;

        parent::__construct($hash, $range, $name);
    }
}

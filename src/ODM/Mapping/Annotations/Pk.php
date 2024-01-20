<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Id\Index as IdIndex;
use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Special field mapping to map document identifiers
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Pk extends AbstractField
{
    public string $keyType = IdIndex::HASH;

    public KeyStrategy $strategy;

    public function __construct(
        ?string $name = IdIndex::HASH,
        ?KeyStrategy $strategy = null,
        ?string $type = null,
        bool $nullable = false,
        array $options = [],
    ) {
        $this->strategy = $strategy ?: new KeyStrategy(KeyStrategy::HASH_STRATEGY_FORMAT);

        parent::__construct($name, $type, $nullable, $options);
    }
}

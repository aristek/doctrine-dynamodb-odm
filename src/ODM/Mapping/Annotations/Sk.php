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
final class Sk extends AbstractField
{
    public string $keyType = IdIndex::RANGE;

    public KeyStrategy $strategy;

    public function __construct(
        ?string $name = IdIndex::RANGE,
        ?string $type = null,
        ?KeyStrategy $strategy = null,
        bool $nullable = false,
        array $options = [],
    ) {
        $this->strategy = $strategy ?: new KeyStrategy(KeyStrategy::RANGE_STRATEGY_FORMAT);

        parent::__construct($name, $type, $nullable, $options);
    }
}

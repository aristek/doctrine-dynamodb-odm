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
    public string $keyField;

    public string $keyType = IdIndex::HASH;

    public string $strategy;

    public function __construct(
        string $name = null,
        ?string $keyField = null,
        ?string $strategy = null,
        ?string $type = null,
        bool $nullable = false,
        array $options = [],
    ) {
        $this->keyField = $keyField ?: IdIndex::HASH;
        $this->strategy = $strategy ?: IndexStrategy::PK_STRATEGY_FORMAT;

        parent::__construct($name, $type, $nullable, $options);
    }
}

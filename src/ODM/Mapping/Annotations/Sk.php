<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
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
    public string $keyField;

    public string $keyType = PrimaryKey::RANGE;

    public string $strategy;

    public function __construct(
        string $name = null,
        ?string $keyField = null,
        ?string $type = null,
        ?string $strategy = null,
        bool $nullable = false,
        array $options = [],
    ) {
        $this->keyField = $keyField ?: PrimaryKey::RANGE;
        $this->strategy = $strategy ?: Strategy::SK_STRATEGY_FORMAT;

        parent::__construct($name, $type, $nullable, $options);
    }
}

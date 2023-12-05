<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Attribute;
use BackedEnum;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Specifies a generic field mapping
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Field extends AbstractField
{
    /**
     * @param class-string<BackedEnum>|null $enumType
     */
    public function __construct(
        string $name = null,
        ?string $type = null,
        bool $nullable = false,
        array $options = [],
        ?string $strategy = null,
        public readonly ?string $enumType = null,
    ) {
        parent::__construct($name, $type, $nullable, $options, $strategy);
    }
}

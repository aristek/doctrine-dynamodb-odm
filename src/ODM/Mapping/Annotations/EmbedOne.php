<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;

/**
 * Embeds a single document
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class EmbedOne extends AbstractField
{
    public bool $embedded = true;

    public function __construct(
        ?string $name = null,
        bool $nullable = false,
        array $options = [],
        public readonly ?string $targetDocument = null,
    ) {
        parent::__construct($name, ClassMetadata::ONE, $nullable, $options);
    }
}

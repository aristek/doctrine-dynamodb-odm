<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Embeds multiple documents
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class EmbedMany extends AbstractField
{
    public bool $embedded = true;

    public function __construct(
        ?string $name = null,
        bool $nullable = false,
        array $options = [],
        public readonly ?string $targetDocument = null,
        public readonly ?string $collectionClass = null,
    ) {
        parent::__construct($name, ClassMetadata::MANY, $nullable, $options);
    }
}

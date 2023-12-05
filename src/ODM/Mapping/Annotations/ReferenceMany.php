<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Specifies a one-to-many relationship to a different document
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ReferenceMany extends AbstractField
{
    public bool $reference = true;

    /**
     * @param string[]|string|null $cascade
     */
    public function __construct(
        ?string $name = null,
        bool $nullable = true,
        array $options = [],
        public readonly ?string $targetDocument = null,
        public readonly array|string|null $cascade = null,
        public readonly ?bool $orphanRemoval = null,
        public readonly ?string $inversedBy = null,
        public readonly ?string $mappedBy = null,
        public readonly ?string $repositoryMethod = null,
        public readonly ?string $collectionClass = null,
    ) {
        parent::__construct($name, ClassMetadata::MANY, $nullable, $options);
    }
}

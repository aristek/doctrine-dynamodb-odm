<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;

/**
 * Specifies a one-to-one relationship to a different document
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class ReferenceOne extends AbstractField
{
    public bool $reference = true;

    /**
     * @param class-string|null                $targetDocument
     * @param string[]|string|null             $cascade
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
    ) {
        parent::__construct($name, ClassMetadata::ONE, $nullable, $options);
    }
}

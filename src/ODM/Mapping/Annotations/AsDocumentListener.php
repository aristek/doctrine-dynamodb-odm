<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Service tag to autoconfigure document listeners.
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class AsDocumentListener
{
    public function __construct(
        public ?string $event = null,
        public ?string $connection = null,
        public ?int $priority = null,
    ) {
    }
}

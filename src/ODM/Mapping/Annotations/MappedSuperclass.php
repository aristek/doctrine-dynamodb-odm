<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Specifies a parent class that other documents may extend to inherit mapping information
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class MappedSuperclass extends AbstractDocument
{
    public function __construct(public readonly ?string $repositoryClass = null)
    {
    }
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Attribute;

/**
 * Must be set on a document class to instruct Doctrine to check for lifecycle callback annotations on public methods.
 *
 * @Annotation
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class HasLifecycleCallbacks implements Annotation
{
}

<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Proxy\Factory;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use ProxyManager\Proxy\GhostObjectInterface;

interface ProxyFactory
{
    /**
     * @param ClassMetadata<object>[] $classes
     */
    public function generateProxyClasses(array $classes): int;

    /**
     * Gets a reference proxy instance for the entity of the given type and identified by the given identifier.
     */
    public function getProxy(ClassMetadata $metadata, mixed $identifier): GhostObjectInterface;
}

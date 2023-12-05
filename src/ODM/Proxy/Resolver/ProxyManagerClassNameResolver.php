<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Proxy\Resolver;

use Doctrine\Persistence\Mapping\ProxyClassNameResolver;
use ProxyManager\Inflector\ClassNameInflectorInterface;
use Aristek\Bundle\DynamodbBundle\ODM\Configuration;

/**
 * @internal
 */
final class ProxyManagerClassNameResolver implements ProxyClassNameResolver
{
    public function __construct(private readonly Configuration $configuration)
    {
    }

    public function getRealClass(string $class): string
    {
        return $this->resolveClassName($class);
    }

    public function resolveClassName(string $className): string
    {
        return $this->getClassNameInflector()->getUserClassName($className);
    }

    private function getClassNameInflector(): ClassNameInflectorInterface
    {
        return $this->configuration->getProxyManagerConfiguration()->getClassNameInflector();
    }
}

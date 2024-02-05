<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM;

use ProxyManager\Proxy\GhostObjectInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry as BaseManagerRegistry;
use function array_keys;

class ManagerRegistry extends BaseManagerRegistry
{
    public function __construct(
        string $name,
        array $connections,
        array $managers,
        string $defaultConnection,
        string $defaultManager,
        ?string $proxyInterfaceName = null,
        ?ContainerInterface $container = null
    ) {
        $this->container = $container;

        if (!$proxyInterfaceName) {
            $proxyInterfaceName = GhostObjectInterface::class;
        }

        parent::__construct($name, $connections, $managers, $defaultConnection, $defaultManager, $proxyInterfaceName);
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * @throws DynamoDBException
     */
    public function getAliasNamespace(string $alias): string
    {
        foreach (array_keys($this->getManagers()) as $name) {
            $objectManager = $this->getManager($name);

            if (!$objectManager instanceof DocumentManager) {
                continue;
            }

            try {
                return $objectManager->getConfiguration()->getDocumentNamespace($alias);
            } catch (DynamoDBException) {
            }
        }

        throw DynamoDBException::unknownDocumentNamespace($alias);
    }
}

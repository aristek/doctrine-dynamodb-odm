<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\DependencyInjection;

use Exception;
use Aristek\Bundle\DynamodbBundle\ODM\Configuration as ODMConfiguration;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Id\UuidGenerator;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Driver\AttributeDriver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class AristekDynamodbExtension extends Extension
{
    /**
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('dynamo_db_item_namespace', $config['item_namespace']);
        $container->setParameter('dynamo_db_src_dir', $config['item_dir']);
        $container->setParameter('dynamo_db_ttl', $config['ttl']);

        if ($config['table']) {
            $container->setParameter('dynamo_db_api_base_table', $config['table']);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));

        $loader->load('services.yaml');

        $cacheDir = $container->getParameter('kernel.cache_dir');

        $attributeDriver = new Definition(AttributeDriver::class);
        $attributeDriver
            ->setFactory([AttributeDriver::class, 'create'])
            ->setArguments([$config['item_dir']]);

        $odmConfig = new Definition(ODMConfiguration::class);
        $odmConfig
            ->addMethodCall('setHydratorDir', [sprintf('%s/odm/hydrators/', $cacheDir)])
            ->addMethodCall('setProxyDir', [sprintf('%s/odm/proxies/', $cacheDir)])
            ->addMethodCall('setHydratorNamespace', ['Hydrators'])
            ->addMethodCall('setMetadataDriverImpl', [$attributeDriver])
            ->addMethodCall('setDynamodbConfig', [$config['dynamodb_config']])
            ->addMethodCall('setDatabase', [$config['table']])
            ->addMethodCall('setUuidVersion', [UuidGenerator::UUID_4]);

        $documentManager = $container->register(DocumentManager::class, DocumentManager::class);
        $documentManager->setFactory([DocumentManager::class, 'create']);
        $documentManager->setArguments([$odmConfig]);
    }
}

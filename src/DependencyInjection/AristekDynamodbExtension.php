<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\DependencyInjection;

use Aristek\Bundle\DynamodbBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Aristek\Bundle\DynamodbBundle\ODM\Configuration as ODMConfiguration;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Id\UuidGenerator;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\AsDocumentListener;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Driver\AttributeDriver;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\ContainerRepositoryFactory;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\ServiceDocumentRepositoryInterface;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;
use function current;
use function sprintf;

final class AristekDynamodbExtension extends Extension implements PrependExtensionInterface
{
    private const CONFIG_DIRECTORY = __DIR__.'/../../config';
    private const EXTENSION_MONOLOG = 'monolog';

    public function getAlias(): string
    {
        return 'aristek_dynamodb';
    }

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

        $container->setParameter('doctrine_dynamodb.odm.connections', ['default']);
        $container->setParameter('doctrine_dynamodb.odm.default_connection', 'default');

        $this->loadDocumentManager($config, $container);

        $container
            ->registerForAutoconfiguration(ServiceDocumentRepositoryInterface::class)
            ->addTag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

        $container->registerAttributeForAutoconfiguration(
            AsDocumentListener::class,
            static function (ChildDefinition $definition, AsDocumentListener $attribute): void {
                $definition->addTag('doctrine_dynamodb.odm.event_listener', [
                    'event'      => $attribute->event,
                    'connection' => $attribute->connection,
                    'priority'   => $attribute->priority,
                ]);
            }
        );
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependMonologExtensionConfig($container);
    }

    /**
     * Loads a document manager configuration.
     */
    protected function loadDocumentManager(array $config, ContainerBuilder $container): void
    {
        $configurationId = 'doctrine_dynamodb.odm.configuration';

        $odmConfigDef = new Definition(ODMConfiguration::class);
        $container->setDefinition($configurationId, $odmConfigDef);

        $cacheDir = $container->getParameter('kernel.cache_dir');

        $attributeDriver = new Definition(AttributeDriver::class);
        $attributeDriver
            ->setFactory([AttributeDriver::class, 'create'])
            ->setArguments([$config['item_dir']]);

        $methods = [
            'setDatabase'           => $config['table'],
            'setDynamodbConfig'     => $config['dynamodb_config'],
            'setMetadataDriverImpl' => $attributeDriver,
            'setProxyDir'           => sprintf('%s/odm/proxies/', $cacheDir),
            'setHydratorDir'        => sprintf('%s/odm/hydrators/', $cacheDir),
            'setHydratorNamespace'  => 'Hydrators',
            'setUuidVersion'        => UuidGenerator::UUID_4,
            'setRepositoryFactory'  => new Reference(ContainerRepositoryFactory::class),
        ];

        foreach ($methods as $method => $arg) {
            if ($odmConfigDef->hasMethodCall($method)) {
                $odmConfigDef->removeMethodCall($method);
            }

            $odmConfigDef->addMethodCall($method, [$arg]);
        }

        $odmDmArgs = [
            new Reference($configurationId),
            // Document managers will share their connection's event manager
            new Reference('doctrine_dynamodb.odm.0_connection.event_manager'),
        ];
        $odmDmDef = new Definition(DocumentManager::class, $odmDmArgs);
        $odmDmDef->setFactory([DocumentManager::class, 'create']);
        $odmDmDef->setLazy(true);
        $odmDmDef->addTag('doctrine_dynamodb.odm.document_manager');
        $odmDmDef->setPublic(true);

        $container->setDefinition('doctrine_dynamodb.odm.document_manager', $odmDmDef);
        $container->setParameter(
            'doctrine_dynamodb.odm.document_managers',
            ['doctrine_dynamodb.odm.document_manager' => 'doctrine_dynamodb.odm.document_manager']
        );

        $container->setParameter(
            'doctrine_dynamodb.odm.default_document_manager',
            'doctrine_dynamodb.odm.document_manager'
        );

        $container->setDefinition(
            'doctrine_dynamodb.odm.0_connection.event_manager',
            new ChildDefinition('doctrine_dynamodb.odm.connection.event_manager'),
        );

        $container->setAlias(
            'doctrine_dynamodb.odm.event_manager',
            new Alias('doctrine_dynamodb.odm.0_connection.event_manager'),
        );
    }

    private function prependMonologExtensionConfig(ContainerBuilder $container): void
    {
        $monologExtensionConfig = current(Yaml::parseFile(self::CONFIG_DIRECTORY.'/packages/monolog.yaml'));

        $container->prependExtensionConfig(self::EXTENSION_MONOLOG, $monologExtensionConfig);
    }
}

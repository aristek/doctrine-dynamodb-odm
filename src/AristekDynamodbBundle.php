<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle;

use Aristek\Bundle\DynamodbBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Aristek\Bundle\DynamodbBundle\ODM\Configuration;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\ManagerRegistry;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterEventListenersAndSubscribersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function assert;
use function spl_autoload_register;
use function spl_autoload_unregister;

final class AristekDynamodbBundle extends Bundle
{
    /** @var callable|null */
    private $autoloader;

    public function boot(): void
    {
        $registry = $this->container->get('aristek_dynamodb');
        assert($registry instanceof ManagerRegistry);

        $this->registerAutoloader($registry->getManager());
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new RegisterEventListenersAndSubscribersPass(
                'doctrine_dynamodb.odm.connections',
                'doctrine_dynamodb.odm.%s_connection.event_manager',
                'doctrine_dynamodb.odm'
            )
        );
        $container->addCompilerPass(new ServiceRepositoryCompilerPass());
    }

    public function shutdown(): void
    {
        $this->unregisterAutoloader();
    }

    private function registerAutoloader(DocumentManager $documentManager): void
    {
        $configuration = $documentManager->getConfiguration();
        if ($configuration->getAutoGenerateProxyClasses() !== Configuration::AUTOGENERATE_FILE_NOT_EXISTS) {
            return;
        }

        $this->autoloader = $configuration->getProxyManagerConfiguration()->getProxyAutoloader();

        spl_autoload_register($this->autoloader);
    }

    private function unregisterAutoloader(): void
    {
        if ($this->autoloader === null) {
            return;
        }

        spl_autoload_unregister($this->autoloader);
        $this->autoloader = null;
    }
}

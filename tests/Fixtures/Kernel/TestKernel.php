<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Fixtures\Kernel;

use Aristek\Bundle\DynamodbBundle\AristekDynamodbBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
{
    private const CONFIG_EXTENSIONS = '.{php,xml,yaml,yml}';

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new AristekDynamodbBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $confDir = __DIR__.'/config';

        $loader->load($confDir.'/framework'.self::CONFIG_EXTENSIONS, 'glob');
        $loader->load($confDir.'/aristek_dynamodb'.self::CONFIG_EXTENSIONS, 'glob');
        $loader->load($confDir.'/services'.self::CONFIG_EXTENSIONS, 'glob');
    }
}

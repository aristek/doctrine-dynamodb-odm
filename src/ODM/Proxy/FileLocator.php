<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Proxy;

use ProxyManager\FileLocator\FileLocator as BaseFileLocator;
use function mkdir;
use function realpath;

/**
 * @internal
 */
final class FileLocator extends BaseFileLocator
{
    public function __construct(string $proxiesDirectory)
    {
        $absolutePath = realpath($proxiesDirectory);

        if ($absolutePath === false) {
            mkdir($proxiesDirectory, 0775, true);
        }

        parent::__construct($proxiesDirectory);
    }
}

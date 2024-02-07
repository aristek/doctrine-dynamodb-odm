<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Command;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbIndex;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;
use function preg_replace;
use function sprintf;
use function str_replace;

abstract class AbstractSchemaCommand extends Command
{
    protected function getDynamodbIndex(PrimaryKey $index): DynamoDbIndex
    {
        $idx = new DynamoDbIndex(hashKey: $index->hashKey->key, rangeKey: $index->rangeKey?->key);
        if ($index->name) {
            $idx->setName($index->name);
        }

        return $idx;
    }

    protected function getManagedItemClasses(string $namespace, string $srcDir): array
    {
        $classes = [];

        $finder = new Finder();
        $finder->in($srcDir)->path('/\.php$/');

        foreach ($finder as $splFileInfo) {
            $classname = sprintf(
                "%s\\%s\\%s",
                $namespace,
                str_replace("/", "\\", $splFileInfo->getRelativePath()),
                $splFileInfo->getBasename(".php")
            );

            $classname = preg_replace('#\\\\+#', '\\', $classname);

            $classes[$classname] = $classname;
        }

        return $classes;
    }
}

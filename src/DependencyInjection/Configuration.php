<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('aristek_dynamodb');

        $tree
            ->getRootNode()
                ->children()
                    ->scalarNode('table')->isRequired()->end()
                    ->scalarNode('item_namespace')->isRequired()->end()
                    ->scalarNode('item_dir')->isRequired()->end()
                    ->integerNode('ttl')->defaultValue(36000)->end()
                    ->arrayNode('dynamodb_config')->isRequired()
                        ->children()
                            ->scalarNode('endpoint')->defaultNull()->end()
                            ->scalarNode('region')->isRequired()->end()
                            ->scalarNode('version')->defaultValue('latest')->end()
                            ->scalarNode('debug')->defaultFalse()->end()
                            ->arrayNode('credentials')
                                ->children()
                                    ->scalarNode('key')->defaultNull()->end()
                                    ->scalarNode('secret')->defaultNull()->end()
                                ->end()
                            ->end()
                ->end()
            ->end();

        return $tree;
    }
}

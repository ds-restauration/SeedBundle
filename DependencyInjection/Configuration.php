<?php

namespace DsRestauration\SeedBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ds_restauration_seed');

        $rootNode
            ->children()
                ->scalarNode('prefix')->defaultValue('seed')
                ->info('The seed command prefix')->end()
                ->scalarNode('directory')->defaultValue('Seeds')
                ->info('The seeds directory')->end()
                ->scalarNode('separator')->defaultValue(':')
                ->info('The seeds separator')->end()
                ->arrayNode('seed_order')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->info('The order to load the seeds')->end()
                ->arrayNode('bundle_order')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->info('The order to load the bundles')->end()
            ->end();

        return $treeBuilder;
    }
}

<?php
/**
 * This file is part of the Networking package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Networking\InitCmsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 * @package Networking\InitCmsBundle\DependencyInjection
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('networking_init_cms');
        //mongodb is not yet fully supported but will come (eventually)
        $supportedDrivers = array('orm', 'mongodb');
        $rootNode
            ->children()
                ->scalarNode('db_driver')
                    ->defaultValue('orm')
                    ->validate()
                    ->ifNotInArray($supportedDrivers)
                    ->thenInvalid('The driver %s is not supported. Please choose one of '.json_encode($supportedDrivers))
                    ->end()
                ->end()
                ->arrayNode('class')
                    ->children()
                        ->scalarNode('page')->cannotBeEmpty()->end()
                        ->scalarNode('layout_block')->defaultValue('Networking\InitCmsBundle\Entity\LayoutBlock')->end()
                        ->scalarNode('user')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->scalarNode('ckeditor_config')->defaultValue('')->end()
                ->scalarNode('translation_fallback_route')->defaultValue('initcms_404')->end()
                ->scalarNode('404_template')->isRequired()->end()
                ->scalarNode('no_translation_template')->isRequired()->end()
                ->arrayNode('languages')
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('label')->isRequired()->end()
                            ->scalarNode('short_label')->end()
                            ->scalarNode('locale')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('content_types')
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('class')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('template')->isRequired()->end()
                            ->scalarNode('name')->isRequired()->end()
                            ->scalarNode('icon')->end()
                            ->scalarNode('controller')->defaultValue('NetworkingInitCmsBundle:FrontendPage:index')->end()
                            ->arrayNode('zones')
                            ->requiresAtLeastOneElement()
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('name')->isRequired()->end()
                                        ->scalarNode('span')->isRequired()->end()
                                        ->scalarNode('max_content_items')->defaultValue(0)->end()
                                        ->arrayNode('restricted_types')->prototype('scalar')->end()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('admin_menu_groups')->requiresAtLeastOneElement()
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('items')->requiresAtLeastOneElement()
                                ->prototype('scalar')->end()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}

<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('phauthentic_aop');
        $root = $tree->getRootNode();
        if (!$root instanceof ArrayNodeDefinition) {
            throw new \LogicException('The root node must be an array node.');
        }
        $children = $root->children();

        $children->booleanNode('enabled')->defaultTrue();
        $children->enumNode('proxy_strategy')
            ->info('auto: subclass proxies for non-final classes, interface proxies for final classes with interfaces')
            ->values(['auto', 'subclass', 'interface'])
            ->defaultValue('auto');
        $children->scalarNode('proxy_dir')->cannotBeEmpty()->defaultValue('%kernel.build_dir%/phauthentic_aop');

        $include = $children->arrayNode('include')
            ->info('Type patterns of classes eligible for weaving')
            ->defaultValue(['App\**']);
        $include->scalarPrototype();

        $exclude = $children->arrayNode('exclude')
            ->info('Type patterns never woven; the AOP library, Symfony, Doctrine and #[Aspect] classes are always excluded')
            ->defaultValue([]);
        $exclude->scalarPrototype();

        return $tree;
    }
}

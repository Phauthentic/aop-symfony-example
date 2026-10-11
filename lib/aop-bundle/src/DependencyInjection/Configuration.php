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

        $children->booleanNode('strip_final')
            ->info('Risky: weave final classes and methods with subclass proxies of a copy whose `final` modifiers are removed; see docs/symfony.md')
            ->defaultFalse();
        $stripInclude = $children->arrayNode('strip_final_include')
            ->info('Type patterns of classes whose `final` may be removed; defaults to the include patterns');
        $stripInclude->scalarPrototype()->cannotBeEmpty();

        $root->validate()
            ->ifTrue(static fn (mixed $config): bool => \is_array($config) && [] === ($config['strip_final_include'] ?? []))
            ->then(static fn (array $config): array => array_replace($config, ['strip_final_include' => $config['include']]));

        return $tree;
    }
}

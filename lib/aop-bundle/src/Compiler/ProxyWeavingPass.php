<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Compiler;

use Phauthentic\AOP\Advisor\Advisor;
use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Exception\ProxyGenerationException;
use Phauthentic\AOP\Pointcut\Pattern\TypePattern;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Proxy\InterfaceProxyGenerator;
use Phauthentic\AOP\Proxy\MethodEligibility;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Proxy\ProxyStrategyResolver;
use Phauthentic\AOP\Proxy\SubclassProxyGenerator;
use Phauthentic\AOP\Symfony\Container\AdvisorRegistryFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces advised services by generated proxies and records what it did in the weaving report.
 *
 * @phpstan-type Report array{advised: array<string, array{class: string, proxy: string, strategy: string, methods: array<string, list<string>>}>, skipped: array<string, array{class: string, reason: string}>}
 */
final class ProxyWeavingPass implements CompilerPassInterface
{
    public const PROXY_DIR = 'phauthentic_aop.proxy_dir';
    public const PROXY_STRATEGY = 'phauthentic_aop.proxy_strategy';
    public const INCLUDE = 'phauthentic_aop.include';
    public const EXCLUDE = 'phauthentic_aop.exclude';
    public const REPORT = 'phauthentic_aop.weaving_report';

    private const RUNTIME = 'phauthentic_aop.runtime';
    private const ALWAYS_EXCLUDED = ['Symfony\**', 'Doctrine\**'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::RUNTIME)) {
            return;
        }

        $bag = $container->getParameterBag();
        $chains = new AdvisorChainFactory(AdvisorRegistryFactory::create(self::aspectClasses($container)), new ArrayAspectInstanceResolver([]), new ParameterBinder());
        $proxyDir = self::stringParameter($container, self::PROXY_DIR);
        $writer = new ProxyClassWriter($proxyDir);
        $configured = 'auto' === ($strategy = self::stringParameter($container, self::PROXY_STRATEGY)) ? null : ProxyStrategy::from($strategy);
        $include = self::patterns($container, self::INCLUDE);
        $exclude = [...self::patterns($container, self::EXCLUDE), ...array_map(static fn (string $p): TypePattern => new TypePattern($p), self::ALWAYS_EXCLUDED)];
        $runtime = $container->getDefinition(self::RUNTIME);

        /** @var Report $report */
        $report = ['advised' => [], 'skipped' => []];
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic() || $definition->hasErrors() || str_starts_with($id, 'phauthentic_aop.')) {
                continue;
            }
            $class = $bag->resolveValue($definition->getClass());
            if (!\is_string($class) || !self::isWeavable($class, $include, $exclude)) {
                continue;
            }
            // Reflect the target only: this tracks its file for container freshness.
            $target = $container->getReflectionClass($class, false);
            if (null === $target || [] !== $target->getAttributes(Aspect::class) || null !== MethodEligibility::isClassProxyable($target)) {
                continue;
            }

            [$strategy, $reason] = self::strategy($target, $configured, $definition);
            $methods = self::advisedMethods($chains, $target, $strategy ?? ProxyStrategy::SUBCLASS, $id);
            if ([] === $methods) {
                continue;
            }
            if (null === $strategy) {
                $report['skipped'][$id] = ['class' => $class, 'reason' => (string) $reason];
                continue;
            }

            $generator = ProxyStrategy::SUBCLASS === $strategy ? new SubclassProxyGenerator() : new InterfaceProxyGenerator();
            // Salted with the service id (bean names are registered per proxy class) and the proxy directory, so a
            // build never reuses a proxy class loaded from another cache directory (e.g. during cache:clear).
            $proxy = $generator->generate($target, array_keys($methods), $id."\0".$proxyDir);
            // The file is what the bundle autoloads at runtime. The dumper needs the class now (lazy services), but
            // it must not be loaded from that file: the dumper would then inline an include of it into lazy ghost
            // class files using `$container->targetDir`, which is undefined there (Symfony 7.2 PhpDumper bug).
            $writer->write($proxy);
            ProxyClassWriter::load($proxy);

            $setRuntime = ['__aopSetRuntime', [new Reference(self::RUNTIME)]];
            if (ProxyStrategy::SUBCLASS === $strategy) {
                $definition->setClass($proxy->className)->addMethodCall(...$setRuntime);
            } else {
                // DecoratorServicePass already ran, so decorate by hand.
                $proxyDefinition = (new Definition($proxy->className, [new Reference($id.'.aop_target')]))
                    ->setPublic($definition->isPublic())
                    ->setShared($definition->isShared())
                    ->setTags($definition->getTags())
                    ->addMethodCall(...$setRuntime);
                $container->setDefinition($id.'.aop_target', $definition->setPublic(false)->setTags([]));
                $container->setDefinition($id, $proxyDefinition);
            }
            $runtime->addMethodCall('registerBeanName', [$proxy->className, $id]);
            $report['advised'][$id] = ['class' => $class, 'proxy' => $proxy->className, 'strategy' => $strategy->value, 'methods' => $methods];
        }

        $container->setParameter(self::REPORT, $report);
        $container->getDefinition('phauthentic_aop.command.debug')->setArgument(0, $report);
    }

    /**
     * @param \ReflectionClass<object> $target
     *
     * @return array{0: ProxyStrategy|null, 1: string|null} strategy, or null and the reason
     */
    private static function strategy(\ReflectionClass $target, ?ProxyStrategy $configured, Definition $definition): array
    {
        try {
            $strategy = (new ProxyStrategyResolver())->resolve($target, $configured);
        } catch (ProxyGenerationException $e) {
            return [null, $e->getMessage()];
        }
        if (null === $strategy) {
            return [null, 'the class is final and implements no interface'];
        }
        if (ProxyStrategy::SUBCLASS === $strategy && null !== $definition->getFactory()) {
            // A factory creates the instance, so its class cannot be swapped; wrap it instead.
            return [] !== $target->getInterfaceNames()
                ? [ProxyStrategy::INTERFACE, null]
                : [null, 'the service is created by a factory, which needs an interface proxy, but the class implements no interface'];
        }

        return [$strategy, null];
    }

    /**
     * @param \ReflectionClass<object> $target
     *
     * @return array<string, list<string>> method name => advisor ids
     */
    private static function advisedMethods(AdvisorChainFactory $chains, \ReflectionClass $target, ProxyStrategy $strategy, string $id): array
    {
        $context = new StaticMatchContext($target, $id, $strategy);
        $methods = [];
        foreach ($target->getMethods() as $method) {
            if (null !== MethodEligibility::reasonForExclusion($method, $strategy, $target)) {
                continue;
            }
            $advisors = $chains->matchedAdvisors($target, $method, $context);
            if ([] !== $advisors) {
                $methods[$method->getName()] = array_map(static fn (Advisor $a): string => $a->id, $advisors);
            }
        }

        return $methods;
    }

    /**
     * @param list<TypePattern> $include
     * @param list<TypePattern> $exclude
     */
    private static function isWeavable(string $class, array $include, array $exclude): bool
    {
        $class = ltrim($class, '\\');
        // The library itself is never woven, its test fixtures may be.
        if (str_starts_with($class, 'Phauthentic\AOP\\') && !str_contains($class, '\Tests\\')) {
            return false;
        }
        foreach ($exclude as $pattern) {
            if ($pattern->matches($class)) {
                return false;
            }
        }
        foreach ($include as $pattern) {
            if ($pattern->matches($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The aspect classes AspectCollectorPass passed to the registry factory, so weaving matches what runs.
     *
     * @return list<string>
     */
    private static function aspectClasses(ContainerBuilder $container): array
    {
        return self::strings($container->getDefinition('phauthentic_aop.advisor_registry')->getArgument(0), 'the advisor registry argument');
    }

    /**
     * @return list<TypePattern>
     */
    private static function patterns(ContainerBuilder $container, string $parameter): array
    {
        return array_map(static fn (string $p): TypePattern => new TypePattern($p), self::strings($container->getParameter($parameter), $parameter));
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value, string $what): array
    {
        $strings = [];
        foreach (\is_array($value) ? $value : [null] as $item) {
            $strings[] = \is_string($item) ? $item : throw new InvalidArgumentException(sprintf('Expected a list of strings for %s.', $what));
        }

        return $strings;
    }

    private static function stringParameter(ContainerBuilder $container, string $parameter): string
    {
        $value = $container->getParameterBag()->resolveValue($container->getParameter($parameter));

        return \is_string($value) ? $value : throw new InvalidArgumentException(sprintf('Parameter "%s" must be a string.', $parameter));
    }
}

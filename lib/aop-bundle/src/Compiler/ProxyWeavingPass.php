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
use Phauthentic\AOP\Symfony\ProxyAutoloader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces advised services by generated proxies and records what it did in the weaving report.
 *
 * @phpstan-type Report array{advised: array<string, array{class: string, proxy: string, strategy: string, methods: array<string, list<string>>, selfInvocations?: list<array{caller: string, method: string, file: string, line: int}>}>, skipped: array<string, array{class: string, reason: string}>}
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

        $autoloadFile = null;
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
            $writer->write($proxy);
            // The dumper needs the class now, but it must not be loaded from its file: the dumper would then inline an
            // include of that file into lazy proxy class files using `$container->targetDir`, which is undefined there
            // (Symfony 7.2 PhpDumper). Hence the service includes the autoload file, which never redeclares a class.
            ProxyClassWriter::load($proxy);
            $autoloadFile ??= self::writeAutoloadFile($proxyDir);

            $setRuntime = ['__aopSetRuntime', [new Reference(self::RUNTIME)]];
            if (ProxyStrategy::SUBCLASS === $strategy) {
                $definition->setClass($proxy->className)->setFile($autoloadFile)->addMethodCall(...$setRuntime);
                if ($definition->isLazy() && !$definition->hasTag('proxy')) {
                    // A lazy ghost would extend the proxy class before the service includes the autoload file; a
                    // lazy proxy of the target class creates the proxied instance in the service factory instead.
                    $definition->addTag('proxy', ['interface' => $class]);
                }
            } else {
                // DecoratorServicePass already ran, so decorate by hand.
                $proxyDefinition = (new Definition($proxy->className, [new Reference($id.'.aop_target')]))
                    ->setPublic($definition->isPublic())
                    ->setShared($definition->isShared())
                    ->setTags($definition->getTags())
                    ->setFile($autoloadFile)
                    ->addMethodCall(...$setRuntime);
                $container->setDefinition($id.'.aop_target', $definition->setPublic(false)->setTags([]));
                $container->setDefinition($id, $proxyDefinition);
            }
            $runtime->addMethodCall('registerBeanName', [$proxy->className, $id]);
            $report['advised'][$id] = ['class' => $class, 'proxy' => $proxy->className, 'strategy' => $strategy->value, 'methods' => $methods];
            if (ProxyStrategy::INTERFACE === $strategy) {
                $report['advised'][$id]['selfInvocations'] = SelfInvocationScanner::scan($target, array_keys($methods));
            }
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
        if (ProxyStrategy::SUBCLASS === $strategy && (null !== $definition->getFactory() || null !== $definition->getFile())) {
            // A factory creates the instance, or the definition's file slot is taken, so the class cannot be swapped; wrap it instead.
            if ([] === $target->getInterfaceNames()) {
                return [null, sprintf('the service %s, which needs an interface proxy, but the class implements no interface', null !== $definition->getFactory() ? 'is created by a factory' : 'includes a file')];
            }
            $strategy = ProxyStrategy::INTERFACE;
        }

        return ProxyStrategy::INTERFACE === $strategy ? self::unlessMethodsWouldBeHidden($target, $definition) : [$strategy, null];
    }

    /**
     * Tags are processed before weaving and may reference any public method of the service (event listeners and
     * subscribers, controllers, message handlers, ...), which an interface proxy only has if an interface declares it.
     *
     * @param \ReflectionClass<object> $target
     *
     * @return array{0: ProxyStrategy|null, 1: string|null}
     */
    private static function unlessMethodsWouldBeHidden(\ReflectionClass $target, Definition $definition): array
    {
        $tags = array_keys($definition->getTags());
        if ([] === $tags) {
            return [ProxyStrategy::INTERFACE, null];
        }

        $declared = [];
        foreach ($target->getInterfaces() as $interface) {
            foreach ($interface->getMethods() as $method) {
                $declared[strtolower($method->getName())] = true;
            }
        }
        $hidden = [];
        foreach ($target->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic() && !$method->isConstructor() && !$method->isDestructor() && !isset($declared[strtolower($method->getName())])) {
                $hidden[] = $method->getName().'()';
            }
        }

        return [] === $hidden
            ? [ProxyStrategy::INTERFACE, null]
            : [null, sprintf('an interface proxy would hide method %s, which tag %s may reference', implode(', ', $hidden), implode(', ', $tags))];
    }

    private static function writeAutoloadFile(string $dir): string
    {
        $file = $dir.'/'.ProxyAutoloader::FILE;
        // Written atomically: a concurrent process may include it at any time.
        $tmp = @tempnam($dir, ProxyAutoloader::FILE);
        if (false === $tmp || false === @file_put_contents($tmp, ProxyAutoloader::fileContents()) || !@rename($tmp, $file)) {
            throw new ProxyGenerationException(sprintf('Cannot write the proxy autoload file "%s".', $file));
        }
        @chmod($file, 0666 & ~umask());

        return $file;
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

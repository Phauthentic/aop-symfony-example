<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Advisor\AdvisorFactory;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Advisor\MetadataNamedPointcutResolver;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Aspect\AspectInstanceResolverInterface;
use Phauthentic\AOP\Aspect\AspectMetadataReader;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Exception\ProxyGenerationException;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Runtime\AopRuntime;

/**
 * Programmatic, framework-free proxy creation.
 */
final class ProxyFactory
{
    private readonly AdvisorChainFactory $chains;
    private readonly ProxyStrategyResolver $strategies;
    private ?AopRuntime $runtime = null;

    /** @var array<string, class-string> */
    private array $classes = [];

    /**
     * @param ProxyStrategy|null $strategy null = auto (see ProxyStrategyResolver)
     * @param string|null        $cacheDir null = eval generated code instead of writing files
     */
    public function __construct(
        AdvisorRegistry $advisors,
        AspectInstanceResolverInterface $aspects,
        private readonly ?ProxyStrategy $strategy = null,
        private readonly ?string $cacheDir = null,
    ) {
        $this->chains = new AdvisorChainFactory($advisors, $aspects, new ParameterBinder());
        $this->strategies = new ProxyStrategyResolver();
    }

    /**
     * @param list<object> $aspectInstances objects of `#[Aspect]` classes
     */
    public static function fromAspects(array $aspectInstances, ?ProxyStrategy $strategy = null, ?string $cacheDir = null): self
    {
        $reader = new AspectMetadataReader();
        $instances = [];
        $metadata = [];
        foreach ($aspectInstances as $aspect) {
            $instances[$aspect::class] = $aspect;
            $metadata[] = $reader->read($aspect::class);
        }

        $pointcuts = new MetadataNamedPointcutResolver($metadata, $reader);
        $factory = new AdvisorFactory();
        $advisors = [];
        foreach ($metadata as $meta) {
            array_push($advisors, ...$factory->fromMetadata($meta, $pointcuts));
        }

        return new self(new AdvisorRegistry($advisors), new ArrayAspectInstanceResolver($instances), $strategy, $cacheDir);
    }

    /**
     * Shared runtime every proxy created here is wired to; call `__aopSetRuntime()` with it on instances
     * constructed from `createProxyClass()`.
     */
    public function runtime(): AopRuntime
    {
        return $this->runtime ??= new AopRuntime($this->chains);
    }

    /**
     * Returns the proxy class name, or `$class` itself when no advisor matches.
     * A subclass proxy is constructed like the target; an interface proxy takes the target instance.
     *
     * @param class-string $class
     *
     * @return class-string
     */
    public function createProxyClass(string $class, ?string $beanName = null): string
    {
        $key = $class.'@'.$beanName;
        if (isset($this->classes[$key])) {
            return $this->classes[$key];
        }

        $target = new \ReflectionClass($class);
        if (null !== MethodEligibility::isClassProxyable($target)) {
            return $this->classes[$key] = $class;
        }

        $strategy = $this->strategies->resolve($target, $this->strategy);
        $methods = $this->advisedMethods($target, $strategy ?? ProxyStrategy::SUBCLASS, $beanName);
        if ([] === $methods) {
            return $this->classes[$key] = $class;
        }
        if (null === $strategy) {
            throw new ProxyGenerationException(sprintf('Class "%s" is advised but cannot be proxied: it is final and implements no interface.', $class));
        }

        $generator = ProxyStrategy::SUBCLASS === $strategy ? new SubclassProxyGenerator() : new InterfaceProxyGenerator();
        $proxy = $generator->generate($target, $methods);
        $proxyClass = ProxyClassWriter::load($proxy, $this->cacheDir);
        if (null !== $beanName) {
            $this->runtime()->registerBeanName($class, $beanName);
        }

        return $this->classes[$key] = $proxyClass;
    }

    /**
     * Constructs an advised instance of `$class` (subclass strategy) with the given constructor arguments.
     * Classes using the interface strategy must be wrapped with createProxy() instead.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function newInstance(string $class, mixed ...$constructorArguments): object
    {
        $proxyClass = $this->createProxyClass($class);
        if (!is_a($proxyClass, $class, true)) {
            throw new ProxyGenerationException(sprintf('Class "%s" uses the interface strategy; construct it yourself and wrap it with createProxy().', $class));
        }

        $instance = new $proxyClass(...$constructorArguments);
        if ($instance instanceof AdvisedInterface) {
            $instance->__aopSetRuntime($this->runtime());
        }

        return $instance;
    }

    /**
     * Wraps an existing instance in an interface proxy. Returns `$target` itself when no advisor matches.
     */
    public function createProxy(object $target, ?string $beanName = null): object
    {
        $proxyClass = $this->createProxyClass($target::class, $beanName);
        if ($proxyClass === $target::class) {
            return $target;
        }
        if (is_subclass_of($proxyClass, $target::class)) {
            throw new ProxyGenerationException(sprintf('Class "%s" uses the subclass strategy, which cannot wrap an existing instance; use createProxyClass() or newInstance() instead.', $target::class));
        }

        $proxy = new $proxyClass($target);
        \assert($proxy instanceof AdvisedInterface);
        $proxy->__aopSetRuntime($this->runtime());

        return $proxy;
    }

    /**
     * @param \ReflectionClass<object> $target
     *
     * @return list<string>
     */
    private function advisedMethods(\ReflectionClass $target, ProxyStrategy $strategy, ?string $beanName): array
    {
        $context = new StaticMatchContext($target, $beanName, $strategy);
        $methods = [];
        foreach ($target->getMethods() as $method) {
            if (null === MethodEligibility::reasonForExclusion($method, $strategy, $target)
                && [] !== $this->chains->matchedAdvisors($target, $method, $context)) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }
}

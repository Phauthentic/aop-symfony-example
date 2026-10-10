<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Exception\ProxyGenerationException;
use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Proxy\ProxyFactory;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Aspect\TargetsAspect;
use Phauthentic\AOP\Tests\Fixtures\Log;
use Phauthentic\AOP\Tests\Fixtures\Service\Greeter;
use Phauthentic\AOP\Tests\Fixtures\Targets\CountingConstructor;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithoutInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\GreetingInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\InterfaceTypedTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\ReadonlyTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\ShoutInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\SignatureShapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProxyFactory::class)]
final class ProxyFactoryTest extends TestCase
{
    private TargetsAspect $aspect;

    protected function setUp(): void
    {
        Log::reset();
        $this->aspect = new TargetsAspect();
    }

    private function factory(?ProxyStrategy $strategy = null, ?string $cacheDir = null): ProxyFactory
    {
        return ProxyFactory::fromAspects([$this->aspect], $strategy, $cacheDir);
    }

    #[Test]
    public function subclassProxyAdvisesCallsAndSelfInvocation(): void
    {
        $factory = $this->factory();
        $before = CountingConstructor::$constructed;

        $proxy = $factory->newInstance(CountingConstructor::class, 'p-');

        self::assertInstanceOf(CountingConstructor::class, $proxy);
        self::assertSame($before + 1, CountingConstructor::$constructed);
        self::assertSame('p-x1', $proxy->work('x'));
        self::assertSame(['around>work', 'work', 'around<'], Log::$entries);

        Log::reset();
        self::assertSame('p-y2', $proxy->selfCall('y'));
        self::assertSame(['around>selfCall', 'around>work', 'work', 'around<', 'around<'], Log::$entries);
        self::assertSame($proxy, $this->aspect->joinPoints[0]->getThis());
        self::assertSame($proxy, $this->aspect->joinPoints[0]->getTarget());
    }

    #[Test]
    public function createProxyClassReturnsAnAdvisedSubclassAndCachesIt(): void
    {
        $factory = $this->factory();
        $class = $factory->createProxyClass(CountingConstructor::class);

        self::assertTrue(is_subclass_of($class, CountingConstructor::class));
        self::assertTrue(is_subclass_of($class, AdvisedInterface::class));
        self::assertSame($class, $factory->createProxyClass(CountingConstructor::class));

        $proxy = new $class('manual-');
        $proxy->__aopSetRuntime($factory->runtime());
        $proxy->work('z');
        self::assertSame(['around>work', 'work', 'around<'], Log::$entries);
    }

    #[Test]
    public function variadicArgumentsReachTheJoinPointInDeclaredShape(): void
    {
        $proxy = $this->factory()->newInstance(SignatureShapes::class);

        self::assertSame('x:{"0":1,"foo":2}', $proxy->variadic('x', 1, foo: 2));
        self::assertSame(['x', 1, 'foo' => 2], $this->aspect->joinPoints[0]->getArgs());
        self::assertSame('plain', $proxy->notAdvised());
        self::assertSame(['around>variadic', 'variadic', 'around<', 'notAdvised'], Log::$entries);
    }

    #[Test]
    public function returnsTheOriginalClassWhenNothingMatches(): void
    {
        $factory = $this->factory();

        self::assertSame(Greeter::class, $factory->createProxyClass(Greeter::class));
        self::assertSame(SignatureShapes::class, $factory->createProxyClass(SignatureShapes::class, 'plain.shapes'));

        $greeter = new Greeter();
        self::assertSame($greeter, $factory->createProxy($greeter));
    }

    #[Test]
    public function finalClassWithoutInterfaceCannotBeProxied(): void
    {
        $this->expectException(ProxyGenerationException::class);
        $this->expectExceptionMessage(FinalWithoutInterface::class);

        $this->factory()->createProxyClass(FinalWithoutInterface::class);
    }

    #[Test]
    public function interfaceProxyWrapsAnExistingInstance(): void
    {
        $target = new FinalWithInterface();
        $proxy = $this->factory()->createProxy($target);

        self::assertInstanceOf(GreetingInterface::class, $proxy);
        self::assertInstanceOf(ShoutInterface::class, $proxy);
        self::assertNotInstanceOf(FinalWithInterface::class, $proxy);
        self::assertSame('Hello Bob', $proxy->greet('Bob'));
        self::assertSame(['around>greet', 'greet', 'around<'], Log::$entries, 'this(FinalWithInterface) must not match an interface proxy');
        self::assertSame($proxy, $this->aspect->joinPoints[0]->getThis());
        self::assertSame($target, $this->aspect->joinPoints[0]->getTarget());
    }

    #[Test]
    public function proxyableAttributeSelectsInterfaceStrategy(): void
    {
        $proxy = $this->factory(ProxyStrategy::SUBCLASS)->createProxy(new InterfaceTypedTarget());

        self::assertInstanceOf(ShoutInterface::class, $proxy);
        self::assertNotInstanceOf(InterfaceTypedTarget::class, $proxy);
        self::assertSame('HEY!', $proxy->shout('hey'));
        self::assertSame(['around>shout', 'shout', 'around<'], Log::$entries);
    }

    #[Test]
    public function createProxyRefusesSubclassStrategy(): void
    {
        $this->expectException(ProxyGenerationException::class);
        $this->expectExceptionMessage('createProxyClass');

        $this->factory(ProxyStrategy::SUBCLASS)->createProxy(new CountingConstructor('x'));
    }

    #[Test]
    public function cacheDirModeWritesAndRequiresProxyFiles(): void
    {
        $dir = sys_get_temp_dir().'/aop-factory-'.bin2hex(random_bytes(6));
        try {
            $factory = $this->factory(null, $dir);
            $proxy = $factory->newInstance(ReadonlyTarget::class, 'ro');

            self::assertSame('ro', $proxy->value());
            self::assertSame(['around>value', 'around<'], Log::$entries);
            self::assertFileExists($dir.'/'.str_replace('\\', '/', $proxy::class).'.php');
        } finally {
            exec('rm -rf '.escapeshellarg($dir));
        }
    }

    #[Test]
    public function beanNameIsUsedForMatching(): void
    {
        $factory = $this->factory();

        self::assertSame(CountingConstructor::class, $factory->createProxyClass(CountingConstructor::class, 'plain.counter'));
        self::assertNotSame(CountingConstructor::class, $factory->createProxyClass(CountingConstructor::class, 'app.counter'));
    }

    #[Test]
    public function eachBeanNameGetsItsOwnProxyClass(): void
    {
        $factory = $this->factory();

        self::assertNotSame(
            $factory->createProxyClass(CountingConstructor::class, 'app.first'),
            $factory->createProxyClass(CountingConstructor::class, 'app.second'),
        );
    }

    #[Test]
    public function acceptsARegistryDirectly(): void
    {
        $factory = new ProxyFactory(new AdvisorRegistry([]), new ArrayAspectInstanceResolver([]));

        self::assertSame(CountingConstructor::class, $factory->createProxyClass(CountingConstructor::class));
    }
}

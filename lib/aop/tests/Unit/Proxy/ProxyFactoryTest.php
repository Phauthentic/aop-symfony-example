<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Exception\AdviceReturnException;
use Phauthentic\AOP\Exception\ProxyGenerationException;
use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Proxy\ProxyFactory;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Aspect\TargetsAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\WrongReturnAspect;
use Phauthentic\AOP\Tests\Fixtures\Log;
use Phauthentic\AOP\Tests\Fixtures\Service\Greeter;
use Phauthentic\AOP\Tests\Fixtures\Targets\Builder;
use Phauthentic\AOP\Tests\Fixtures\Targets\BuilderInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\CountingConstructor;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithoutInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\GreetingInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\InterfaceTypedTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\Narrowed;
use Phauthentic\AOP\Tests\Fixtures\Targets\NarrowedInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\NewStaticTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\ReaderRepo;
use Phauthentic\AOP\Tests\Fixtures\Targets\ReadonlyTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\RepoInterface;
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

        $proxy = $factory->newInstance(CountingConstructor::class, ['p-']);

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
            $proxy = $factory->newInstance(ReadonlyTarget::class, ['ro']);

            self::assertSame('ro', $proxy->value());
            self::assertSame(['around>value', 'around<'], Log::$entries);
            self::assertFileExists($dir.'/'.str_replace('\\', '/', $proxy::class).'.php');
        } finally {
            exec('rm -rf '.escapeshellarg($dir));
        }
    }

    #[Test]
    public function newStaticInsideTheTargetReturnsAnAdvisedInstance(): void
    {
        $proxy = $this->factory()->newInstance(NewStaticTarget::class);

        $created = $proxy->withNewStatic();
        self::assertInstanceOf(AdvisedInterface::class, $created);
        self::assertNotSame($proxy, $created);

        Log::reset();
        self::assertSame('new-static', $created->name());
        self::assertSame(['around>name', 'around<'], Log::$entries);
    }

    #[Test]
    public function runtimeIsSharedByAllInstancesOfAProxyClass(): void
    {
        $factory = $this->factory();
        $class = $factory->createProxyClass(NewStaticTarget::class);
        $first = new $class();
        $second = new $class();
        self::assertInstanceOf(NewStaticTarget::class, $second);
        self::assertInstanceOf(AdvisedInterface::class, $first);
        $first->__aopSetRuntime($factory->runtime());

        self::assertSame('new-static', $second->name());
        self::assertSame(['around>name', 'around<'], Log::$entries);
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

    #[Test]
    public function interfaceProxyTakesSignaturesFromTheTargetWhenInterfacesDeclareDifferentReturnTypes(): void
    {
        $proxy = $this->factory()->createProxy(new ReaderRepo());

        self::assertInstanceOf(RepoInterface::class, $proxy);
        self::assertInstanceOf(\stdClass::class, $proxy->find());
        self::assertSame(['around>find', 'find', 'around<'], Log::$entries);
    }

    #[Test]
    public function interfaceProxyHandlesAChildInterfaceNarrowingAReturnTypeListedAfterItsParent(): void
    {
        $proxy = $this->factory()->createProxy(new Narrowed());

        self::assertInstanceOf(NarrowedInterface::class, $proxy);
        self::assertInstanceOf(\stdClass::class, $proxy->get());
        self::assertSame(['around>get', 'get', 'around<'], Log::$entries);
    }

    #[Test]
    public function interfaceProxyWrapsTargetInstancesReturnedByWithers(): void
    {
        $proxy = $this->factory()->createProxy(new Builder());
        self::assertInstanceOf(BuilderInterface::class, $proxy);

        $copy = $proxy->with('a');

        self::assertInstanceOf(BuilderInterface::class, $copy);
        self::assertInstanceOf(AdvisedInterface::class, $copy);
        self::assertNotSame($proxy, $copy);
        self::assertSame('a', $copy->value());
        self::assertSame('', $proxy->value(), 'the original stays untouched');

        Log::reset();
        $copy->with('b');
        self::assertSame(['around>with', 'with b', 'around<'], Log::$entries, 'the wrapped copy is advised');

        $selfCopy = $proxy->copy();
        self::assertInstanceOf(AdvisedInterface::class, $selfCopy, 'a `self` return type on the target is served as `static`');
        self::assertNotSame($proxy, $selfCopy);
    }

    #[Test]
    public function interfaceProxyReturnsItselfWhenTheTargetReturnsThis(): void
    {
        $proxy = $this->factory()->createProxy(new Builder());
        self::assertInstanceOf(BuilderInterface::class, $proxy);

        self::assertSame($proxy, $proxy->same());
        Log::reset();
        self::assertSame($proxy, $proxy->add('a')->add('b'), 'an interface return type returning $this yields the proxy');
        self::assertSame(['around>add', 'add a', 'around<', 'around>add', 'add b', 'around<'], Log::$entries, 'chained calls are advised');
        self::assertSame('a,b', $proxy->value());
    }

    #[Test]
    public function interfaceProxyLeavesUnrelatedObjectResultsUntouched(): void
    {
        $proxy = $this->factory()->createProxy(new Builder());
        self::assertInstanceOf(BuilderInterface::class, $proxy);

        self::assertSame(\stdClass::class, $proxy->other()::class);
    }

    #[Test]
    public function newInstanceRegistersTheBeanName(): void
    {
        $factory = $this->factory();

        $plain = $factory->newInstance(CountingConstructor::class, ['p-'], 'plain.counter');
        $named = $factory->newInstance(CountingConstructor::class, ['p-'], 'app.counter');

        Log::reset();
        $plain->work('x');
        self::assertSame(['work'], Log::$entries, 'bean(plain.*) is excluded by the pointcut');
        Log::reset();
        $named->work('x');
        self::assertSame(['around>work', 'work', 'around<'], Log::$entries);
    }

    #[Test]
    public function aroundAdviceReturningTheWrongTypeIsReportedAsAdviceError(): void
    {
        $aspect = new WrongReturnAspect();
        $worker = ProxyFactory::fromAspects([$aspect])->newInstance(CountingConstructor::class, ['p-']);

        $this->expectException(AdviceReturnException::class);
        $this->expectExceptionMessage('Around advice Phauthentic\\AOP\\Tests\\Fixtures\\Aspect\\WrongReturnAspect::replace returned int, but CountingConstructor::work(..) is declared to return string.');

        $worker->work('x');
    }

    #[Test]
    public function aroundAdviceReturningACompatibleValuePasses(): void
    {
        $aspect = new WrongReturnAspect();
        $aspect->value = 'replaced';
        $worker = ProxyFactory::fromAspects([$aspect])->newInstance(CountingConstructor::class, ['p-']);

        self::assertSame('replaced', $worker->work('x'));
    }
}

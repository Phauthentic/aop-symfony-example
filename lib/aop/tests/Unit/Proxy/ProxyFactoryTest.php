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
use Phauthentic\AOP\Tests\Fixtures\Aspect\IntroductionAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\IntroductionOnlyAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RefAroundAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RefBeforeAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\TargetsAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\WrongReturnAspect;
use Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable;
use Phauthentic\AOP\Tests\Fixtures\Introduction\BareTarget;
use Phauthentic\AOP\Tests\Fixtures\Introduction\FinalWorkTarget;
use Phauthentic\AOP\Tests\Fixtures\Introduction\ReadonlyWorkTarget;
use Phauthentic\AOP\Tests\Fixtures\Introduction\WorkInterface;
use Phauthentic\AOP\Tests\Fixtures\Introduction\WorkTarget;
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
use Phauthentic\AOP\Tests\Fixtures\Targets\RefTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\RefTargetInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\RepoInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\SelfCallingFinal;
use Phauthentic\AOP\Tests\Fixtures\Targets\SelfCallingInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\ShoutInterface;
use Phauthentic\AOP\Tests\Fixtures\Targets\SignatureShapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function interfaceProxyTargetAdvisesItsSelfCallThroughAopContext(): void
    {
        $proxy = $this->factory()->createProxy(new SelfCallingFinal());

        self::assertInstanceOf(SelfCallingInterface::class, $proxy);
        self::assertSame('outer>inner', $proxy->outer());
        self::assertSame(['around>outer', 'around>inner', 'around<', 'around<'], Log::$entries);
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

    /**
     * @return iterable<string, array{ProxyStrategy}>
     */
    public static function strategies(): iterable
    {
        yield 'subclass' => [ProxyStrategy::SUBCLASS];
        yield 'interface' => [ProxyStrategy::INTERFACE];
    }

    /**
     * @param list<object> $aspects
     */
    private static function refTarget(ProxyStrategy $strategy, array $aspects): RefTargetInterface
    {
        $factory = ProxyFactory::fromAspects($aspects, $strategy);
        $proxy = ProxyStrategy::SUBCLASS === $strategy ? $factory->newInstance(RefTarget::class) : $factory->createProxy(new RefTarget());
        \assert($proxy instanceof RefTargetInterface && $proxy instanceof AdvisedInterface);

        return $proxy;
    }

    #[Test]
    #[DataProvider('strategies')]
    public function beforeAdviceSeesByReferenceValuesWithoutChangingThem(ProxyStrategy $strategy): void
    {
        $aspect = new RefBeforeAspect();
        $proxy = self::refTarget($strategy, [$aspect]);

        $n = 1;
        $proxy->increment($n);
        self::assertSame(2, $n, 'the target writes through to the caller, advice overwriting its copies does not');
        $list = ['a'];
        self::assertSame(3, $proxy->appendAll($list, 'b', 'c'));
        self::assertSame(['a', 'b', 'c'], $list);
        $a = 1;
        $b = 2;
        $proxy->swap($a, $b);
        self::assertSame([2, 1], [$a, $b]);
        $x = 1;
        $y = 2;
        $proxy->scaleAll(3, $x, $y);
        self::assertSame([3, 6], [$x, $y]);

        self::assertSame([[1], [['a'], 'b', 'c'], [1, 2], [3, 1, 2]], $aspect->args);
        self::assertSame([1], $aspect->bound, 'args(n) binds the value');
    }

    #[Test]
    #[DataProvider('strategies')]
    public function aroundProceedingOnceWritesThroughToTheCaller(ProxyStrategy $strategy): void
    {
        $proxy = self::refTarget($strategy, [new RefAroundAspect()]);

        $n = 1;
        $proxy->increment($n);
        self::assertSame(2, $n);
        $x = 1;
        $y = 2;
        $proxy->scaleAll(3, $x, $y);
        self::assertSame([3, 6], [$x, $y]);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function aroundProceedingWithReplacedArgumentsGivesTheCallerTheTargetResult(ProxyStrategy $strategy): void
    {
        $around = new RefAroundAspect();
        $before = new RefBeforeAspect();
        $proxy = self::refTarget($strategy, [$around, $before]);

        $around->replacement = [5];
        $n = 1;
        $proxy->increment($n);
        self::assertSame(6, $n);
        self::assertSame([5], $before->bound, 'inner advice sees the replaced value');

        $around->replacement = [3, 10, 20];
        $x = 1;
        $y = 2;
        $proxy->scaleAll(3, $x, $y);
        self::assertSame([30, 60], [$x, $y], 'variadic by-reference slots are written back too');

        $around->replacement = [['z']];
        $list = ['a'];
        self::assertSame(1, $proxy->appendAll($list));
        self::assertSame(['z'], $list);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function aroundNotProceedingLeavesTheCallerVariableUntouched(ProxyStrategy $strategy): void
    {
        $around = new RefAroundAspect();
        $around->proceed = false;
        $proxy = self::refTarget($strategy, [$around]);

        $n = 1;
        $proxy->increment($n);
        self::assertSame(1, $n);
    }

    /**
     * @return iterable<string, array{ProxyStrategy|null, class-string}>
     */
    public static function introductionTargets(): iterable
    {
        yield 'subclass' => [null, WorkTarget::class];
        yield 'subclass, readonly target' => [null, ReadonlyWorkTarget::class];
        yield 'interface' => [ProxyStrategy::INTERFACE, FinalWorkTarget::class];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('introductionTargets')]
    public function introducedInterfaceIsUsableAndAdvisable(?ProxyStrategy $strategy, string $class): void
    {
        $factory = ProxyFactory::fromAspects([new IntroductionAspect()], $strategy);
        $proxy = match ($class) {
            WorkTarget::class => $factory->newInstance(WorkTarget::class),
            ReadonlyWorkTarget::class => $factory->newInstance(ReadonlyWorkTarget::class, ['worked ']),
            default => $factory->createProxy(new FinalWorkTarget()),
        };
        self::assertInstanceOf(AdvisedInterface::class, $proxy);
        self::assertInstanceOf(Auditable::class, $proxy);
        self::assertInstanceOf(WorkInterface::class, $proxy);

        self::assertSame('worked x', $proxy->work('x'));
        self::assertSame(['introduced recordAudit'], Log::$entries, 'this(auditable) bound the proxy, so the recording call was advised too');

        Log::reset();
        self::assertSame(['work(x)'], $proxy->auditTrail());
        self::assertSame(['introduced auditTrail'], Log::$entries, 'execution(* Auditable::*(..)) matches introduced methods, target() and within() do not');
    }

    #[Test]
    public function aClassWithOnlyAnIntroductionStillGetsAProxy(): void
    {
        $proxy = ProxyFactory::fromAspects([new IntroductionOnlyAspect()])->newInstance(BareTarget::class);

        self::assertInstanceOf(AdvisedInterface::class, $proxy);
        self::assertInstanceOf(Auditable::class, $proxy);
        $proxy->recordAudit('a');
        self::assertSame(['a'], $proxy->auditTrail());
        self::assertSame('bare', $proxy->value());
    }
}

<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Proxy\GeneratedProxy;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Proxy\SubclassProxyGenerator;
use Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable;
use Phauthentic\AOP\Tests\Fixtures\Introduction\AuditTrail;
use Phauthentic\AOP\Tests\Fixtures\Introduction\ReadonlyWorkTarget;
use Phauthentic\AOP\Tests\Fixtures\Introduction\WorkInterface;
use Phauthentic\AOP\Tests\Fixtures\Introduction\WorkTarget;
use Phauthentic\AOP\Tests\Fixtures\Log;
use Phauthentic\AOP\Tests\Fixtures\Targets\AttributedTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\CountingConstructor;
use Phauthentic\AOP\Tests\Fixtures\Targets\LegacyCountable;
use Phauthentic\AOP\Tests\Fixtures\Targets\ReadonlyTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\RefTarget;
use Phauthentic\AOP\Tests\Fixtures\Targets\SelfReferencing;
use Phauthentic\AOP\Tests\Fixtures\Targets\SelfReferencingBase;
use Phauthentic\AOP\Tests\Fixtures\Targets\SignatureShapes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubclassProxyGenerator::class)]
#[CoversClass(GeneratedProxy::class)]
final class SubclassProxyGeneratorTest extends TestCase
{
    private const SHAPES = ['nullable', 'union', 'intersection', 'dnf', 'nothing', 'explode', 'defaults', 'variadic', 'untyped', 'guarded'];

    protected function setUp(): void
    {
        Log::reset();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param list<string>    $methods
     *
     * @return class-string<T&AdvisedInterface>
     */
    private function load(string $class, array $methods): string
    {
        // Salted per test: the runtime is static per proxy class, so a class shared between tests would leak it.
        $proxyClass = ProxyClassWriter::load((new SubclassProxyGenerator())->generate(new \ReflectionClass($class), $methods, $this->name()));
        if (!is_a($proxyClass, $class, true) || !is_a($proxyClass, AdvisedInterface::class, true)) {
            self::fail($proxyClass.' is not an advised subclass of '.$class);
        }

        return $proxyClass;
    }

    #[Test]
    public function describesTheGeneratedProxy(): void
    {
        $generated = (new SubclassProxyGenerator())->generate(new \ReflectionClass(SignatureShapes::class), ['union', 'nullable']);

        self::assertSame(ProxyStrategy::SUBCLASS, $generated->strategy);
        self::assertSame(['nullable', 'union'], $generated->advisedMethods);
        self::assertStringStartsWith('Phauthentic\AOP\Generated\Phauthentic\AOP\Tests\Fixtures\Targets\SignatureShapesProxy_', $generated->className);
        self::assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $generated->code);
    }

    #[Test]
    public function proxyIsANonFinalSubclassImplementingAdvisedInterface(): void
    {
        $class = $this->load(SignatureShapes::class, self::SHAPES);
        $rc = new \ReflectionClass($class);

        self::assertTrue($rc->isSubclassOf(SignatureShapes::class));
        self::assertTrue($rc->implementsInterface(AdvisedInterface::class));
        self::assertFalse($rc->isFinal());
        self::assertFalse($rc->isReadOnly());
        self::assertInstanceOf(SignatureShapes::class, new $class());
    }

    #[Test]
    public function overriddenMethodsKeepTheirExactSignature(): void
    {
        $class = $this->load(SignatureShapes::class, self::SHAPES);

        foreach (self::SHAPES as $method) {
            $proxyMethod = new \ReflectionMethod($class, $method);
            self::assertSame($class, $proxyMethod->getDeclaringClass()->getName(), $method.' must be overridden');
            self::assertSame(
                ProxyTestHelper::signature(new \ReflectionMethod(SignatureShapes::class, $method)),
                ProxyTestHelper::signature($proxyMethod),
                $method,
            );
        }
    }

    #[Test]
    public function constructorAndUnadvisedMethodsAreNotOverridden(): void
    {
        $class = $this->load(SignatureShapes::class, self::SHAPES);

        self::assertSame(SignatureShapes::class, (new \ReflectionMethod($class, 'notAdvised'))->getDeclaringClass()->getName());
        self::assertSame(SignatureShapes::class, (new \ReflectionMethod($class, '__construct'))->getDeclaringClass()->getName());
        self::assertSame('custom', (new $class('custom'))->label);
    }

    #[Test]
    public function withoutRuntimeEveryMethodCallsTheParentDirectly(): void
    {
        $class = $this->load(SignatureShapes::class, self::SHAPES);
        $proxy = new $class();
        $original = new SignatureShapes();

        self::assertSame($original->defaults(), $proxy->defaults());
        self::assertSame($original->union('u'), $proxy->union('u'));
        self::assertSame($original->nullable(null), $proxy->nullable(null));
        self::assertSame($original->dnf(new \ArrayObject([1, 2])), $proxy->dnf(new \ArrayObject([1, 2])));
        self::assertSame($original->intersection(new \ArrayObject([1])), $proxy->intersection(new \ArrayObject([1])));
        self::assertSame($original->untyped('a'), $proxy->untyped('a'));
        self::assertSame($original->variadic('x', 1, 2), $proxy->variadic('x', 1, 2));
        self::assertSame('x:{"0":1,"foo":2}', $proxy->variadic('x', 1, foo: 2));
        self::assertSame('guarded', $proxy->callGuarded());
        $proxy->nothing('n');
        self::assertContains('nothing:n', Log::$entries);

        $this->expectExceptionMessage('bang');
        $proxy->explode('bang');
    }

    #[Test]
    public function withRuntimeMethodsRouteThroughTheRuntime(): void
    {
        $class = $this->load(SignatureShapes::class, self::SHAPES);
        $proxy = new $class();
        $proxy->__aopSetRuntime(ProxyTestHelper::emptyRuntime());

        self::assertSame((new SignatureShapes())->defaults(), $proxy->defaults());
        self::assertSame('x:{"0":1,"foo":2}', $proxy->variadic('x', 1, foo: 2));
        self::assertSame('guarded', $proxy->callGuarded());
        $proxy->nothing('n');
        self::assertContains('nothing:n', Log::$entries);

        $this->expectExceptionMessage('bang');
        $proxy->explode('bang');
    }

    #[Test]
    public function selfAndParentTypesResolveToTheDeclaringClasses(): void
    {
        $methods = ['withSelf', 'makeSelf', 'withParent', 'unionSelf', 'fromBase'];
        $class = $this->load(SelfReferencing::class, $methods);

        foreach ($methods as $method) {
            self::assertSame(
                ProxyTestHelper::signature(new \ReflectionMethod(SelfReferencing::class, $method)),
                ProxyTestHelper::signature(new \ReflectionMethod($class, $method)),
                $method,
            );
        }
        self::assertSame('static', (string) (new \ReflectionMethod($class, 'withSelf'))->getReturnType());

        $proxy = new $class();
        self::assertSame($proxy, $proxy->withSelf(new SelfReferencing()));
        self::assertInstanceOf(SelfReferencing::class, $proxy->makeSelf());
        $base = new SelfReferencingBase();
        self::assertSame($base, $proxy->withParent($base));
        self::assertSame($base, $proxy->fromBase($base));
    }

    #[Test]
    public function keepsOnlySensitiveParameterAttributes(): void
    {
        $class = $this->load(AttributedTarget::class, ['login']);
        $method = new \ReflectionMethod($class, 'login');

        self::assertSame([], $method->getAttributes());
        [$user, $password, $note] = $method->getParameters();
        self::assertSame([], $user->getAttributes());
        self::assertCount(1, $password->getAttributes(\SensitiveParameter::class));
        self::assertSame([], $note->getAttributes());
        self::assertSame('bob:6', (new $class())->login('bob', 'secret'));
    }

    #[Test]
    public function readonlyParentGivesReadonlyProxy(): void
    {
        $class = $this->load(ReadonlyTarget::class, ['value']);

        self::assertTrue((new \ReflectionClass($class))->isReadOnly());
        $proxy = new $class('v');
        self::assertSame('v', $proxy->value());
        $proxy->__aopSetRuntime(ProxyTestHelper::emptyRuntime());
        self::assertSame('v', $proxy->value());
    }

    #[Test]
    public function constructorRunsExactlyOnce(): void
    {
        $class = $this->load(CountingConstructor::class, ['work', 'selfCall']);
        $before = CountingConstructor::$constructed;

        $proxy = new $class('p-');
        $proxy->__aopSetRuntime(ProxyTestHelper::emptyRuntime());

        self::assertSame('p-x1', $proxy->work('x'));
        self::assertSame('p-y2', $proxy->selfCall('y'));
        self::assertSame($before + 1, CountingConstructor::$constructed);
    }

    #[Test]
    public function generatingTwiceReusesTheLoadedClass(): void
    {
        $generator = new SubclassProxyGenerator();
        $first = $generator->generate(new \ReflectionClass(CountingConstructor::class), ['work']);
        ProxyClassWriter::load($first);
        $second = $generator->generate(new \ReflectionClass(CountingConstructor::class), ['work']);
        ProxyClassWriter::load($second);

        self::assertSame($first->className, $second->className);
        self::assertSame($first->code, $second->code);
        self::assertTrue(class_exists($second->className, false));
    }

    #[Test]
    public function keepsReturnTypeWillChangeOfUntypedMethods(): void
    {
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        });
        try {
            $proxyClass = $this->load(LegacyCountable::class, ['count']);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $notices);
        self::assertCount(3, new $proxyClass());
    }

    #[Test]
    public function byReferenceParametersWriteThroughToTheCallerWithAndWithoutRuntime(): void
    {
        $class = $this->load(RefTarget::class, ['appendAll', 'increment', 'scaleAll', 'swap']);
        self::assertSame(
            ProxyTestHelper::signature(new \ReflectionMethod(RefTarget::class, 'appendAll')),
            ProxyTestHelper::signature(new \ReflectionMethod($class, 'appendAll')),
        );
        self::assertSame(
            ProxyTestHelper::signature(new \ReflectionMethod(RefTarget::class, 'scaleAll')),
            ProxyTestHelper::signature(new \ReflectionMethod($class, 'scaleAll')),
        );

        foreach ([null, ProxyTestHelper::emptyRuntime()] as $runtime) {
            $proxy = new $class();
            if (null !== $runtime) {
                $proxy->__aopSetRuntime($runtime);
            }
            $n = 1;
            $proxy->increment($n);
            self::assertSame(2, $n);
            $list = ['a'];
            self::assertSame(3, $proxy->appendAll($list, 'b', 'c'));
            self::assertSame(['a', 'b', 'c'], $list);
            $a = 'a';
            $b = 'b';
            $proxy->swap($a, $b);
            self::assertSame(['b', 'a'], [$a, $b]);
            $x = 1;
            $y = 2;
            $proxy->scaleAll(10, $x, $y);
            self::assertSame([10, 20], [$x, $y]);
        }
    }

    /**
     * @param class-string<WorkInterface> $class
     * @param list<string>                $methods
     *
     * @return class-string<WorkInterface&Auditable&AdvisedInterface>
     */
    private function loadIntroducing(string $class, array $methods = []): string
    {
        $generated = (new SubclassProxyGenerator())->generate(new \ReflectionClass($class), $methods, $this->name(), [Auditable::class => AuditTrail::class]);
        self::assertSame([Auditable::class => AuditTrail::class], $generated->introductions);
        $proxyClass = ProxyClassWriter::load($generated);
        if (!is_a($proxyClass, $class, true) || !is_a($proxyClass, Auditable::class, true) || !is_a($proxyClass, AdvisedInterface::class, true)) {
            self::fail($proxyClass.' is not an advised, auditable subclass of '.$class);
        }

        return $proxyClass;
    }

    #[Test]
    public function introducedInterfaceIsImplementedByDelegatingToOneDefaultImplementationPerInstance(): void
    {
        $class = $this->loadIntroducing(WorkTarget::class);
        $created = AuditTrail::$created;

        $first = new $class();
        $second = new $class();
        self::assertInstanceOf(WorkTarget::class, $first);
        self::assertInstanceOf(Auditable::class, $first);
        self::assertSame($created, AuditTrail::$created, 'the default implementation is created lazily');

        $first->recordAudit('a');
        $first->recordAudit('b');
        $second->recordAudit('c');

        self::assertSame(['a', 'b'], $first->auditTrail());
        self::assertSame(['c'], $second->auditTrail());
        self::assertSame($created + 2, AuditTrail::$created);
        self::assertSame('worked x', $first->work('x'));
    }

    #[Test]
    public function readonlyTargetsGetIntroductionsToo(): void
    {
        $class = $this->loadIntroducing(ReadonlyWorkTarget::class);
        $proxy = new $class('p-');

        self::assertTrue((new \ReflectionClass($class))->isReadOnly());
        self::assertInstanceOf(Auditable::class, $proxy);
        $proxy->recordAudit('a');
        self::assertSame(['a'], $proxy->auditTrail());
        self::assertSame('p-x', $proxy->work('x'));
    }

    #[Test]
    public function advisedIntroducedMethodsRouteThroughTheRuntime(): void
    {
        $class = $this->loadIntroducing(WorkTarget::class, ['auditTrail', 'recordAudit']);
        $proxy = new $class();
        $proxy->__aopSetRuntime(ProxyTestHelper::emptyRuntime());

        self::assertInstanceOf(Auditable::class, $proxy);
        $proxy->recordAudit('a');
        self::assertSame(['a'], $proxy->auditTrail());
    }
}

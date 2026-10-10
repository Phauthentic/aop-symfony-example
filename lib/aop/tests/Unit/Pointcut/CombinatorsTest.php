<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Pointcut;

use Phauthentic\AOP\Exception\BindingException;
use Phauthentic\AOP\Pointcut\AndPointcut;
use Phauthentic\AOP\Pointcut\BindingKind;
use Phauthentic\AOP\Pointcut\BindingSpec;
use Phauthentic\AOP\Pointcut\NotPointcut;
use Phauthentic\AOP\Pointcut\OrPointcut;
use Phauthentic\AOP\Pointcut\PointcutInterface;
use Phauthentic\AOP\Pointcut\RuntimeMatchContext;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Pointcut\TargetPointcut;
use Phauthentic\AOP\Pointcut\TruePointcut;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\UserRepository;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\SpyPointcut;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AndPointcut::class)]
#[CoversClass(OrPointcut::class)]
#[CoversClass(NotPointcut::class)]
#[CoversClass(TruePointcut::class)]
final class CombinatorsTest extends TestCase
{
    private function sctx(): StaticMatchContext
    {
        return new StaticMatchContext(Reflect::cls(UserRepository::class));
    }

    private function method(): \ReflectionMethod
    {
        return new \ReflectionMethod(UserRepository::class, 'find');
    }

    private function rctx(): RuntimeMatchContext
    {
        return new RuntimeMatchContext(new UserRepository(), new UserRepository(), $this->method(), [], $this->sctx());
    }

    private function method_(PointcutInterface $p): bool
    {
        return $p->matchesClass($this->sctx()->targetClass, $this->sctx()) && $p->matchesMethod($this->method(), $this->sctx());
    }

    #[Test]
    public function truePointcutMatchesEverything(): void
    {
        $p = new TruePointcut();

        self::assertTrue($this->method_($p));
        self::assertFalse($p->isRuntime());
        self::assertSame([], $p->bindings());
        self::assertTrue($p->matchesRuntime($this->rctx()));
    }

    #[Test]
    public function andShortCircuitsOnClass(): void
    {
        $left = new SpyPointcut(class: false);
        $right = new SpyPointcut();
        $and = new AndPointcut($left, $right);

        self::assertFalse($and->matchesClass($this->sctx()->targetClass, $this->sctx()));
        self::assertSame(1, $left->classCalls);
        self::assertSame(0, $right->classCalls);
    }

    #[Test]
    public function andShortCircuitsOnMethod(): void
    {
        $left = new SpyPointcut(method: false);
        $right = new SpyPointcut();

        self::assertFalse((new AndPointcut($left, $right))->matchesMethod($this->method(), $this->sctx()));
        self::assertSame(0, $right->methodCalls);
        self::assertTrue((new AndPointcut(new SpyPointcut(), $right))->matchesMethod($this->method(), $this->sctx()));
        self::assertSame(1, $right->methodCalls);
    }

    #[Test]
    public function orShortCircuits(): void
    {
        $left = new SpyPointcut();
        $right = new SpyPointcut();
        $or = new OrPointcut($left, $right);

        self::assertTrue($or->matchesMethod($this->method(), $this->sctx()));
        self::assertTrue($or->matchesClass($this->sctx()->targetClass, $this->sctx()));
        self::assertSame(0, $right->methodCalls);
        self::assertSame(0, $right->classCalls);
        self::assertTrue((new OrPointcut(new SpyPointcut(false, false), $right))->matchesMethod($this->method(), $this->sctx()));
        self::assertFalse((new OrPointcut(new SpyPointcut(false, false), new SpyPointcut(false, false)))->matchesMethod($this->method(), $this->sctx()));
    }

    #[Test]
    public function notInvertsStaticResults(): void
    {
        self::assertFalse($this->method_(new NotPointcut(new SpyPointcut())));
        self::assertTrue($this->method_(new NotPointcut(new SpyPointcut(method: false))));
        self::assertTrue((new NotPointcut(new SpyPointcut(class: false)))->matchesClass($this->sctx()->targetClass, $this->sctx()));
    }

    #[Test]
    public function runtimePropagation(): void
    {
        $static = new SpyPointcut();
        $runtime = new SpyPointcut(runtime: true);

        self::assertFalse((new AndPointcut($static, $static))->isRuntime());
        self::assertTrue((new AndPointcut($static, $runtime))->isRuntime());
        self::assertTrue((new AndPointcut($runtime, $static))->isRuntime());
        self::assertFalse((new OrPointcut($static, $static))->isRuntime());
        self::assertTrue((new OrPointcut($static, $runtime))->isRuntime());
        self::assertFalse((new NotPointcut($static))->isRuntime());
        self::assertTrue((new NotPointcut($runtime))->isRuntime());
    }

    #[Test]
    public function andRuntimeShortCircuits(): void
    {
        $left = new SpyPointcut(runtime: true, runtimeResult: false);
        $right = new SpyPointcut(runtime: true);

        self::assertFalse((new AndPointcut($left, $right))->matchesRuntime($this->rctx()));
        self::assertSame(0, $right->runtimeCalls);
        self::assertTrue((new AndPointcut(new SpyPointcut(runtime: true), $right))->matchesRuntime($this->rctx()));
    }

    #[Test]
    public function andRuntimeSkipsRuntimeOfStaticChildren(): void
    {
        $static = new SpyPointcut();
        $runtime = new SpyPointcut(runtime: true);

        self::assertTrue((new AndPointcut($static, $runtime))->matchesRuntime($this->rctx()));
        self::assertSame(0, $static->runtimeCalls);
    }

    #[Test]
    public function orRuntimeUsesStaticResultOfStaticBranch(): void
    {
        // static branch matches -> the failing runtime branch must not veto the OR
        $or = new OrPointcut(new SpyPointcut(), new SpyPointcut(runtime: true, runtimeResult: false));
        self::assertTrue($or->matchesRuntime($this->rctx()));

        // static branch does not match -> only the runtime branch decides
        $or = new OrPointcut(new SpyPointcut(false, false), new SpyPointcut(runtime: true, runtimeResult: false));
        self::assertFalse($or->matchesRuntime($this->rctx()));
        $or = new OrPointcut(new SpyPointcut(false, false), new SpyPointcut(runtime: true, runtimeResult: true));
        self::assertTrue($or->matchesRuntime($this->rctx()));
    }

    #[Test]
    public function notInvertsRuntimeResults(): void
    {
        self::assertFalse((new NotPointcut(new SpyPointcut(runtime: true)))->matchesRuntime($this->rctx()));
        self::assertTrue((new NotPointcut(new SpyPointcut(runtime: true, runtimeResult: false)))->matchesRuntime($this->rctx()));
    }

    #[Test]
    public function notOfRuntimeChildDoesNotPruneStatically(): void
    {
        $not = new NotPointcut(new SpyPointcut(runtime: true));

        self::assertTrue($not->matchesMethod($this->method(), $this->sctx()), 'child static result is only a pre-filter');
    }

    #[Test]
    public function notRuntimeConsidersStaticPartOfChild(): void
    {
        // child static part fails -> child is false -> Not is true
        $not = new NotPointcut(new SpyPointcut(false, false, true, true));

        self::assertTrue($not->matchesRuntime($this->rctx()));
    }

    #[Test]
    public function bindingsAreMergedForAndOr(): void
    {
        $a = new TargetPointcut(new BindingSpec('a', BindingKind::TARGET, null));
        $b = new TargetPointcut(new BindingSpec('b', BindingKind::TARGET, null));

        self::assertSame(['a', 'b'], array_keys((new AndPointcut($a, $b))->bindings()));
        self::assertSame(['a', 'b'], array_keys((new OrPointcut($a, $b))->bindings()));
    }

    #[Test]
    public function notCannotCarryBindings(): void
    {
        $this->expectException(BindingException::class);

        new NotPointcut(new TargetPointcut(new BindingSpec('a', BindingKind::TARGET, null)));
    }

    #[Test]
    public function notHasNoBindings(): void
    {
        self::assertSame([], (new NotPointcut(new SpyPointcut()))->bindings());
    }
}

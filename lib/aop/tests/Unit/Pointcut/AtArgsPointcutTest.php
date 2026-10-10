<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Pointcut;

use Phauthentic\AOP\Pointcut\AtArgsPointcut;
use Phauthentic\AOP\Pointcut\BindingKind;
use Phauthentic\AOP\Pointcut\Expression\PointcutExpressionCompiler;
use Phauthentic\AOP\Pointcut\PointcutInterface;
use Phauthentic\AOP\Pointcut\RuntimeMatchContext;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Marker;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Plain;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\UserRepository;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AtArgsPointcut::class)]
final class AtArgsPointcutTest extends TestCase
{
    /**
     * @param array<int|string, string|null> $names
     */
    private function pc(string $expression, array $names = []): PointcutInterface
    {
        return (new PointcutExpressionCompiler())->compile($expression, $names);
    }

    /**
     * @param list<mixed> $args
     */
    private function ctx(array $args, string $method = 'find'): RuntimeMatchContext
    {
        return new RuntimeMatchContext(new UserRepository(), new UserRepository(), new \ReflectionMethod(UserRepository::class, $method), $args);
    }

    #[Test]
    public function isRuntimeAndPrunesByArity(): void
    {
        $pc = $this->pc('@args('.Marker::class.')');
        $static = new StaticMatchContext(Reflect::cls(UserRepository::class));

        self::assertTrue($pc->isRuntime());
        self::assertTrue($pc->matchesMethod(new \ReflectionMethod(UserRepository::class, 'find'), $static));
        self::assertFalse($pc->matchesMethod(new \ReflectionMethod(UserRepository::class, 'twoStrings'), $static));
        self::assertTrue($this->pc('@args('.Marker::class.', ..)')->matchesMethod(new \ReflectionMethod(UserRepository::class, 'twoStrings'), $static));
    }

    #[Test]
    public function matchesWhenArgumentClassCarriesAttribute(): void
    {
        $pc = $this->pc('@args('.Marker::class.')');

        self::assertTrue($pc->matchesRuntime($this->ctx([new UserRepository()])));
        self::assertFalse($pc->matchesRuntime($this->ctx([new Plain()])));
        self::assertFalse($pc->matchesRuntime($this->ctx(['string'])));
        self::assertFalse($pc->matchesRuntime($this->ctx([null])));
    }

    #[Test]
    public function wildcardAndEllipsis(): void
    {
        $pc = $this->pc('@args(*, '.Marker::class.', ..)');

        self::assertTrue($pc->matchesRuntime($this->ctx([1, new UserRepository(), 3], 'intThenAny')));
        self::assertFalse($pc->matchesRuntime($this->ctx([1, new Plain(), 3], 'intThenAny')));
        self::assertFalse($pc->matchesRuntime($this->ctx([1])));
    }

    #[Test]
    public function bindsAttributeInstance(): void
    {
        $pc = $this->pc('@args(marker)', ['marker' => Marker::class]);
        $ctx = $this->ctx([new UserRepository()]);

        self::assertTrue($pc->matchesRuntime($ctx));
        $marker = $ctx->bound()['marker'];
        self::assertInstanceOf(Marker::class, $marker);
        self::assertSame('user', $marker->label);
    }

    #[Test]
    public function bindingRequiresTheAttribute(): void
    {
        $pc = $this->pc('@args(marker)', ['marker' => Marker::class]);

        self::assertFalse($pc->matchesRuntime($this->ctx([new Plain()])));
    }

    #[Test]
    public function exposesBindingSpecs(): void
    {
        $bindings = $this->pc('@args(marker)', ['marker' => Marker::class])->bindings();

        self::assertSame(BindingKind::AT_ARGS, $bindings['marker']->kind);
        self::assertSame(Marker::class, $bindings['marker']->typeRestriction);
    }
}

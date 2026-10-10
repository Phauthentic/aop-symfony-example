<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Interceptor;

use Phauthentic\AOP\Interceptor\MethodInterceptorInterface;
use Phauthentic\AOP\Interceptor\RuntimeGuardInterceptor;
use Phauthentic\AOP\JoinPoint\MethodInvocation;
use Phauthentic\AOP\JoinPoint\MethodSignature;
use Phauthentic\AOP\Pointcut\Expression\PointcutExpressionCompiler;
use Phauthentic\AOP\Pointcut\PointcutInterface;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use Phauthentic\AOP\Tests\Fixtures\Service\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuntimeGuardInterceptor::class)]
#[CoversClass(MethodInvocation::class)]
final class RuntimeGuardInterceptorTest extends TestCase
{
    /**
     * @param list<mixed> $args
     */
    private function invokeChain(PointcutInterface $pointcut, array $args, ?\Closure $inner = null): mixed
    {
        $target = new UserService();
        $static = new StaticMatchContext(Reflect::cls(UserService::class));
        $guard = new RuntimeGuardInterceptor(
            $pointcut,
            new class($inner) implements MethodInterceptorInterface {
                public function __construct(private readonly ?\Closure $inner)
                {
                }

                public function invoke(MethodInvocation $invocation): mixed
                {
                    ($this->inner ?? static fn (): null => null)($invocation);

                    return $invocation->proceed();
                }
            },
            $static,
        );

        return (new MethodInvocation(
            $target,
            $target,
            MethodSignature::fromReflection(new \ReflectionMethod(UserService::class, 'findUser')),
            $args,
            [$guard],
            static fn (array $a): string => 'target',
        ))->proceed();
    }

    #[Test]
    public function skipsInnerInterceptorWhenRuntimeMatchFails(): void
    {
        $called = false;
        $pointcut = (new PointcutExpressionCompiler())->compile('args(string, ..)');

        $result = $this->invokeChain($pointcut, [1], function () use (&$called): void {
            $called = true;
        });

        self::assertFalse($called);
        self::assertSame('target', $result);
    }

    #[Test]
    public function runsInnerInterceptorWithBoundValuesWhenMatching(): void
    {
        $bound = null;
        $pointcut = (new PointcutExpressionCompiler())->compile('args(id, ..)', ['id' => 'int']);

        $result = $this->invokeChain($pointcut, [7, 'flag'], function (MethodInvocation $invocation) use (&$bound): void {
            $bound = $invocation->getBoundArguments();
        });

        self::assertSame(['id' => 7], $bound);
        self::assertSame('target', $result);
    }

    #[Test]
    public function receivesProxyTargetMethodAndArguments(): void
    {
        $pointcut = new class implements PointcutInterface {
            public ?\Phauthentic\AOP\Pointcut\RuntimeMatchContext $ctx = null;

            public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
            {
                return true;
            }

            public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
            {
                return true;
            }

            public function isRuntime(): bool
            {
                return true;
            }

            public function matchesRuntime(\Phauthentic\AOP\Pointcut\RuntimeMatchContext $ctx): bool
            {
                $this->ctx = $ctx;

                return true;
            }

            public function bindings(): array
            {
                return [];
            }
        };

        $this->invokeChain($pointcut, [3]);

        self::assertNotNull($pointcut->ctx);
        self::assertSame('findUser', $pointcut->ctx->method->getName());
        self::assertSame([3], $pointcut->ctx->args);
        self::assertInstanceOf(UserService::class, $pointcut->ctx->target);
        self::assertNotNull($pointcut->ctx->staticContext);
    }

    #[Test]
    public function boundArgumentsSurviveFurtherProceedCalls(): void
    {
        $invocation = new MethodInvocation(
            new UserService(),
            new UserService(),
            MethodSignature::fromReflection(new \ReflectionMethod(UserService::class, 'findUser')),
            [1],
            [],
            static fn (array $a): null => null,
        );

        self::assertSame([], $invocation->getBoundArguments());
        $with = $invocation->withBoundArguments(['a' => 1]);
        self::assertSame(['a' => 1], $with->getBoundArguments());
        self::assertSame([], $invocation->getBoundArguments());
    }
}

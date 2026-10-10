<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Interceptor;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Interceptor\AfterAdviceInterceptor;
use Phauthentic\AOP\Tests\Fixtures\Aspect\ProbeAspect;
use Phauthentic\AOP\Tests\Fixtures\Chain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AfterAdviceInterceptor::class)]
final class AfterAdviceInterceptorTest extends TestCase
{
    private ProbeAspect $probe;

    protected function setUp(): void
    {
        $this->probe = new ProbeAspect();
    }

    private function interceptor(string $method, ?string $returning = null, ?string $throwing = null): AfterAdviceInterceptor
    {
        return new AfterAdviceInterceptor(Chain::resolver($this->probe), ProbeAspect::class, $method, Chain::plan($method, AdviceType::AFTER, $returning, $throwing));
    }

    #[Test]
    public function runsOnReturn(): void
    {
        $result = Chain::invocation([$this->interceptor('plain')], static fn (): string => 'ok')->proceed();

        self::assertSame('ok', $result);
        self::assertCount(1, $this->probe->calls);
    }

    #[Test]
    public function runsOnThrowAndOriginalPropagates(): void
    {
        $e = new \RuntimeException('x');

        try {
            Chain::invocation([$this->interceptor('plain')], static fn () => throw $e)->proceed();
            self::fail('expected exception');
        } catch (\RuntimeException $caught) {
            self::assertSame($e, $caught);
        }

        self::assertCount(1, $this->probe->calls);
    }

    #[Test]
    public function exceptionFromAdviceReplacesOriginal(): void
    {
        $this->probe->throwFromAdvice = new \LogicException('from advice');

        $this->expectException(\LogicException::class);
        Chain::invocation([$this->interceptor('plain')], static fn () => throw new \RuntimeException('x'))->proceed();
    }
}

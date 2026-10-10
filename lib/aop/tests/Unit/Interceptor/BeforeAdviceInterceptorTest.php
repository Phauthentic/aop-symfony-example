<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Interceptor;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Interceptor\BeforeAdviceInterceptor;
use Phauthentic\AOP\Tests\Fixtures\Aspect\ProbeAspect;
use Phauthentic\AOP\Tests\Fixtures\Chain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BeforeAdviceInterceptor::class)]
final class BeforeAdviceInterceptorTest extends TestCase
{
    private ProbeAspect $probe;

    protected function setUp(): void
    {
        $this->probe = new ProbeAspect();
    }

    private function interceptor(string $method, ?string $returning = null, ?string $throwing = null): BeforeAdviceInterceptor
    {
        return new BeforeAdviceInterceptor(Chain::resolver($this->probe), ProbeAspect::class, $method, Chain::plan($method, AdviceType::BEFORE, $returning, $throwing));
    }

    #[Test]
    public function runsAdviceThenTargetWithUnchangedArguments(): void
    {
        $seen = null;
        $result = Chain::invocation([$this->interceptor('plain')], static function (array $args) use (&$seen): string {
            $seen = $args;

            return 'done';
        })->proceed();

        self::assertSame('done', $result);
        self::assertSame(['World'], $seen);
        self::assertSame([['plain', ['World']]], $this->probe->calls);
    }

    #[Test]
    public function adviceExceptionStopsTheTarget(): void
    {
        $this->probe->throwFromAdvice = new \LogicException('no');
        $called = false;

        try {
            Chain::invocation([$this->interceptor('plain')], static function () use (&$called): null {
                $called = true;

                return null;
            })->proceed();
            self::fail('expected exception');
        } catch (\LogicException) {
        }

        self::assertFalse($called);
    }
}

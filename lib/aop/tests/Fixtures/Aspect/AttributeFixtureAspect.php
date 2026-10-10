<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\After;
use Phauthentic\AOP\Attribute\AfterReturning;
use Phauthentic\AOP\Attribute\AfterThrowing;
use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\Attribute\Pointcut;
use Phauthentic\AOP\Attribute\Proxyable;
use Phauthentic\AOP\Proxy\ProxyStrategy;

#[Aspect]
#[Order(10)]
#[Proxyable(ProxyStrategy::INTERFACE)]
final class AttributeFixtureAspect
{
    #[Pointcut('execution(* App\Service\*Service::*(..))')]
    public function servicePointcut(): void
    {
    }

    #[Before('servicePointcut()')]
    public function logBefore(): void
    {
    }

    #[After('servicePointcut()')]
    public function logAfter(): void
    {
    }

    #[Around('servicePointcut()')]
    public function wrapAround(): void
    {
    }

    #[AfterReturning('servicePointcut()', returning: 'result')]
    public function logReturn(): void
    {
    }

    #[AfterReturning('servicePointcut()')]
    public function logReturnUnbound(): void
    {
    }

    #[AfterThrowing('servicePointcut()', throwing: 'error')]
    public function logThrow(): void
    {
    }
}

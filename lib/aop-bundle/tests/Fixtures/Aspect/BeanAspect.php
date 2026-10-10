<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Attribute\Order;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

#[Aspect]
#[Order(1)]
final class BeanAspect
{
    #[Before('bean(fixture.service)')]
    public function before(JoinPointInterface $jp): void
    {
        Log::add('bean:'.$jp->getSignature()->methodName);
    }
}

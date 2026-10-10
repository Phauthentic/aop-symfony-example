<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Attribute\Around;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\JoinPoint\JoinPointInterface;
use Phauthentic\AOP\JoinPoint\ProceedingJoinPointInterface;
use Phauthentic\AOP\Tests\Fixtures\Log;

/**
 * Advises the proxy target fixtures used by ProxyFactoryTest.
 */
#[Aspect]
final class TargetsAspect
{
    /** @var list<JoinPointInterface> */
    public array $joinPoints = [];

    #[Around('execution(public * Phauthentic\AOP\Tests\Fixtures\Targets\**::*(..)) && !execution(* Phauthentic\AOP\Tests\Fixtures\Targets\**::notAdvised(..)) && !bean(plain.*)')]
    public function around(ProceedingJoinPointInterface $pjp): mixed
    {
        $this->joinPoints[] = $pjp;
        Log::add('around>'.$pjp->getSignature()->methodName);
        try {
            return $pjp->proceed();
        } finally {
            Log::add('around<');
        }
    }

    #[Before('execution(* Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface::*(..)) && this(Phauthentic\AOP\Tests\Fixtures\Targets\FinalWithInterface)')]
    public function onlyForSubclassProxies(): void
    {
        Log::add('this(FinalWithInterface)');
    }
}

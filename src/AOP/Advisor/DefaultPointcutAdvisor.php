<?php

declare(strict_types=1);

namespace App\AOP\Advisor;

use App\AOP\Advisor;
use App\AOP\Advice;
use App\AOP\Pointcut;

/**
 * Default implementation of the Advisor interface.
 * 
 * This is equivalent to Spring's DefaultPointcutAdvisor.
 */
class DefaultPointcutAdvisor implements Advisor
{
    private Advice $advice;
    private Pointcut $pointcut;

    public function __construct(Advice $advice, Pointcut $pointcut)
    {
        $this->advice = $advice;
        $this->pointcut = $pointcut;
    }

    public function getAdvice(): Advice
    {
        return $this->advice;
    }

    public function getPointcut(): Pointcut
    {
        return $this->pointcut;
    }

    public function matches(string $class, string $method): bool
    {
        return $this->pointcut->matches($class, $method);
    }
}

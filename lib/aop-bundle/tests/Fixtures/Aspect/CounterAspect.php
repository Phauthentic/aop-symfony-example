<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect;

use Phauthentic\AOP\Aspect\AspectScope;
use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Before;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

#[Aspect(scope: AspectScope::PER_TARGET)]
final class CounterAspect
{
    private int $calls = 0;

    #[Before('execution(* Phauthentic\AOP\Symfony\Tests\Fixtures\Service\CountedService::work(..))')]
    public function count(): void
    {
        Log::add(sprintf('counter#%d:%d', spl_object_id($this), ++$this->calls));
    }
}

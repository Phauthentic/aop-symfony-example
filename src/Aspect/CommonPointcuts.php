<?php

declare(strict_types=1);

namespace App\Aspect;

use Phauthentic\AOP\Attribute\Aspect;
use Phauthentic\AOP\Attribute\Pointcut;

#[Aspect]
final class CommonPointcuts
{
    #[Pointcut('execution(* App\Service\**::*(..))')]
    public function serviceLayer(): void
    {
    }
}

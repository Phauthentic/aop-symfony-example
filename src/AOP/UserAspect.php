<?php

declare(strict_types=1);

namespace App\AOP;

#[Aspect]
class UserAspect {

    ##[Around("execution(* App\\Service\\UserTestClass::test(..))")]
    #[Around('App\Service\UserTestClass::foo')]
    public function aroundTest(JoinPoint $joinPoint) {
        echo "Before method execution\n";
        $result = $joinPoint->proceed();
        echo "After method execution\n";

        return $result;
    }
}

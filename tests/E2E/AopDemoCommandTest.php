<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AopDemoCommandTest extends KernelTestCase
{
    #[Test]
    public function printsResultsAndTheAdviceSequence(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:aop-demo'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(<<<'OUT'
            reverse('hello') = olleh
            upper('hello') = HELLO
            upper('hello') = HELLO
            greet('World', 2) = Hello, World! Hello, World!
            greet('') failed: InvalidArgumentException: Name must not be empty
            flaky('x') = x
            placeOrder('SKU-1', 2) = Order SKU-1 x2 placed
            StringService audit trail: reverse, upper, upper, greet, flaky
            OrderService audit trail: placeOrder
            Trace:
            log before StringService::reverse(..)
            log after StringService::reverse(..)
            log before StringService::upper(..)
            cache miss upper(hello)
            log after StringService::upper(..)
            log before StringService::upper(..)
            cache hit upper(hello)
            log after StringService::upper(..)
            log before StringService::greet(..)
            log after StringService::greet(..)
            log before StringService::greet(..)
            log after StringService::greet(..)
            log before StringService::flaky(..)
            retry attempt 1/3
            retry attempt 1 failed: flaky failure 1
            retry attempt 2/3
            retry attempt 2 failed: flaky failure 2
            retry attempt 3/3
            log after StringService::flaky(..)
            log before OrderService::placeOrder(..)
            log after OrderService::placeOrder(..)

            OUT, $tester->getDisplay());
    }
}

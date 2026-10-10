<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Service\OrderService;
use App\Service\StringService;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugAopCommandTest extends KernelTestCase
{
    #[Test]
    public function listsAdvisorsPerMethodAndTheProxyStrategy(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('debug:aop'));

        self::assertSame(Command::SUCCESS, $tester->execute([], ['decorated' => false]));
        $output = $tester->getDisplay();

        self::assertMatchesRegularExpression('/'.preg_quote(StringService::class, '/').'\s+subclass/', $output);
        self::assertMatchesRegularExpression('/'.preg_quote(OrderService::class, '/').'\s+interface/', $output);
        foreach (['reverse', 'upper', 'greet', 'flaky'] as $method) {
            self::assertStringContainsString($method, $output);
        }
        foreach (['LoggingAspect::before', 'ValidationAspect::before', 'RetryAspect::around', 'CachingAspect::around'] as $advisor) {
            self::assertStringContainsString($advisor, $output);
        }
    }
}

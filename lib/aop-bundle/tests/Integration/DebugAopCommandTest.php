<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Integration;

use Phauthentic\AOP\Symfony\Command\DebugAopCommand;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\BeanAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\CounterAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\LogAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\Auditable;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\AuditTrail;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalNoInterfaceService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\OrderSubscriber;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\SelfCallingService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DebugAopCommand::class)]
final class DebugAopCommandTest extends TestCase
{
    #[Test]
    public function listsAdvisedMethodsAndSkippedServices(): void
    {
        $kernel = new TestKernel(TestKernel::newBaseDir());
        try {
            $tester = new CommandTester((new Application($kernel))->find('debug:aop'));

            self::assertSame(Command::SUCCESS, $tester->execute([]));
            $output = $tester->getDisplay();
        } finally {
            $kernel->shutdown();
        }

        self::assertStringContainsString('fixture.service', $output);
        self::assertStringContainsString('greet', $output);
        self::assertStringContainsString(BeanAspect::class.'::before', $output);
        self::assertStringContainsString(LogAspect::class.'::around', $output);
        self::assertStringContainsString(CounterAspect::class.' (pertarget)::count', $output);
        self::assertStringNotContainsString(LogAspect::class.' (singleton)', $output);
        self::assertMatchesRegularExpression('/'.preg_quote(FinalService::class, '/').'\s+interface/', $output);
        self::assertStringContainsString(FinalNoInterfaceService::class, $output);
        self::assertStringContainsString('final and implements no interface', $output);
        self::assertStringContainsString(OrderSubscriber::class, $output);
        self::assertStringContainsString('an interface proxy would hide method onOrderPlaced(), which tag kernel.event_subscriber may reference', $output);
        self::assertStringContainsString('Introduced interfaces', $output);
        self::assertMatchesRegularExpression('/fixture\.audited\s+'.preg_quote(Auditable::class, '/').'\s+'.preg_quote(AuditTrail::class, '/').'/', $output);
    }

    #[Test]
    public function listsSelfInvocationsThatBypassAdvice(): void
    {
        $kernel = new TestKernel(TestKernel::newBaseDir());
        try {
            $tester = new CommandTester((new Application($kernel))->find('debug:aop'));
            $tester->execute([]);
            $output = preg_replace('/\s+/', ' ', $tester->getDisplay());
        } finally {
            $kernel->shutdown();
        }

        self::assertIsString($output);
        self::assertStringContainsString('Self-invocations that bypass advice', $output);
        self::assertStringContainsString(SelfCallingService::class.' '.SelfCallingService::class.'::outer() -> inner()', $output);
        self::assertStringContainsString('SelfCallingService.php:11', $output);
        self::assertStringContainsString('AopContext::currentProxy()', $output);
        self::assertStringNotContainsString(FinalService::class.' '.FinalService::class.'::', $output);
    }
}

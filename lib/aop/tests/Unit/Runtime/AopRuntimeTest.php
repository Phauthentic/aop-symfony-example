<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Runtime;

use Phauthentic\AOP\Advice\AdviceDefinition;
use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Advisor\Advisor;
use Phauthentic\AOP\Advisor\AdvisorChainFactory;
use Phauthentic\AOP\Advisor\AdvisorFactory;
use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Advisor\MetadataNamedPointcutResolver;
use Phauthentic\AOP\Aspect\ArrayAspectInstanceResolver;
use Phauthentic\AOP\Aspect\AspectMetadata;
use Phauthentic\AOP\Aspect\AspectMetadataReader;
use Phauthentic\AOP\Binding\ParameterBinder;
use Phauthentic\AOP\Pointcut\Expression\PointcutExpressionCompiler;
use Phauthentic\AOP\Runtime\AopRuntime;
use Phauthentic\AOP\Tests\Fixtures\Aspect\NamedPointcutAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\ProbeAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RecordingAspectA;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RecordingAspectB;
use Phauthentic\AOP\Tests\Fixtures\Log;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\SpyPointcut;
use Phauthentic\AOP\Tests\Fixtures\Service\Greeter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AopRuntime::class)]
final class AopRuntimeTest extends TestCase
{
    private int $resolved = 0;

    protected function setUp(): void
    {
        Log::reset();
        $this->resolved = 0;
    }

    private function resolver(): ArrayAspectInstanceResolver
    {
        $make = function (string $class): \Closure {
            return function () use ($class): object {
                ++$this->resolved;

                return new $class();
            };
        };

        return new ArrayAspectInstanceResolver([
            RecordingAspectA::class => $make(RecordingAspectA::class),
            RecordingAspectB::class => $make(RecordingAspectB::class),
            NamedPointcutAspect::class => $make(NamedPointcutAspect::class),
        ]);
    }

    /**
     * @param class-string ...$aspects
     */
    private function runtime(string ...$aspects): AopRuntime
    {
        $reader = new AspectMetadataReader();
        $metas = array_map(static fn (string $c): AspectMetadata => $reader->read($c), $aspects);
        $resolver = new MetadataNamedPointcutResolver($metas);
        $advisors = [];
        foreach ($metas as $meta) {
            array_push($advisors, ...(new AdvisorFactory())->fromMetadata($meta, $resolver));
        }

        return new AopRuntime(new AdvisorChainFactory(new AdvisorRegistry($advisors), $this->resolver(), new ParameterBinder()));
    }

    private function call(AopRuntime $rt, Greeter $g, string $name = 'World'): mixed
    {
        return $rt->invoke($g, $g, Greeter::class, 'greet', [$name], static fn (array $a): mixed => $g->greet($name));
    }

    #[Test]
    public function twoAspectsFiveAdviceTypesRunInSpecOrder(): void
    {
        $rt = $this->runtime(RecordingAspectB::class, RecordingAspectA::class);

        self::assertSame('Hello World', $this->call($rt, new Greeter()));

        self::assertSame([
            'A.around>', 'A.before',
            'B.around>', 'B.before',
            'target',
            'B.afterReturning', 'B.after', 'B.around<',
            'A.afterReturning', 'A.after', 'A.around<',
        ], Log::$entries);
    }

    #[Test]
    public function exceptionPathRunsAfterThrowingAndAfterAndRethrows(): void
    {
        $rt = $this->runtime(RecordingAspectA::class, RecordingAspectB::class);
        $g = new Greeter();

        try {
            $rt->invoke($g, $g, Greeter::class, 'fail', [], static fn (array $a): mixed => $g->fail());
            self::fail('expected exception');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame([
            'A.around>', 'A.before',
            'B.around>', 'B.before',
            'target',
            'B.afterThrowing', 'B.after', 'B.around<',
            'A.afterThrowing', 'A.after', 'A.around<',
        ], Log::$entries);
    }

    #[Test]
    public function aspectsAreResolvedOnceAndChainIsCachedPerMethod(): void
    {
        $spy = new SpyPointcut();
        $def = new AdviceDefinition(ProbeAspect::class, 'plain', AdviceType::BEFORE, 'x', null, null, 0);
        $probe = new ProbeAspect();
        $rt = new AopRuntime(new AdvisorChainFactory(
            new AdvisorRegistry([new Advisor('p', $def, 1, $spy)]),
            new ArrayAspectInstanceResolver([ProbeAspect::class => function () use ($probe): ProbeAspect {
                ++$this->resolved;

                return $probe;
            }]),
            new ParameterBinder(),
        ));
        $g = new Greeter();

        $this->call($rt, $g);
        $this->call($rt, $g);
        $this->call($rt, $g);

        self::assertSame(1, $spy->classCalls);
        self::assertSame(1, $spy->methodCalls);
        self::assertSame(1, $this->resolved);
        self::assertCount(3, $probe->calls);

        $rt->invoke($g, $g, Greeter::class, 'fail', [], static fn (): null => null);
        self::assertSame(2, $spy->methodCalls);
    }

    #[Test]
    public function noMatchingAdvisorCallsTargetDirectly(): void
    {
        $rt = new AopRuntime(new AdvisorChainFactory(new AdvisorRegistry([]), $this->resolver(), new ParameterBinder()));

        self::assertSame('Hello X', $this->call($rt, new Greeter(), 'X'));
        self::assertSame(['target'], Log::$entries);
    }

    #[Test]
    public function runtimePointcutBindingsReachAdvice(): void
    {
        $rt = $this->runtime(NamedPointcutAspect::class);

        $this->call($rt, new Greeter(), 'Ann');

        self::assertSame(['N.local', 'N.name=Ann', 'target'], Log::$entries);
    }

    #[Test]
    public function registeredBeanNamesFeedBeanPointcuts(): void
    {
        $def = new AdviceDefinition(ProbeAspect::class, 'plain', AdviceType::BEFORE, 'bean(my.greeter)', null, null, 0);
        $probe = new ProbeAspect();
        $pointcut = (new PointcutExpressionCompiler())->compile('bean(my.greeter)');
        $rt = new AopRuntime(new AdvisorChainFactory(
            new AdvisorRegistry([new Advisor('p', $def, 1, $pointcut)]),
            new ArrayAspectInstanceResolver([ProbeAspect::class => $probe]),
            new ParameterBinder(),
        ));
        $g = new Greeter();

        $this->call($rt, $g);
        self::assertCount(0, $probe->calls);

        $rt = new AopRuntime(new AdvisorChainFactory(
            new AdvisorRegistry([new Advisor('p', $def, 1, $pointcut)]),
            new ArrayAspectInstanceResolver([ProbeAspect::class => $probe]),
            new ParameterBinder(),
        ));
        $rt->registerBeanName(Greeter::class, 'my.greeter');
        $this->call($rt, $g);
        self::assertCount(1, $probe->calls);
    }

    #[Test]
    public function beanNameRegisteredForAProxyClassAppliesToThatClassOnly(): void
    {
        $def = new AdviceDefinition(ProbeAspect::class, 'plain', AdviceType::BEFORE, 'bean(my.greeter)', null, null, 0);
        $probe = new ProbeAspect();
        $rt = new AopRuntime(new AdvisorChainFactory(
            new AdvisorRegistry([new Advisor('p', $def, 1, (new PointcutExpressionCompiler())->compile('bean(my.greeter)'))]),
            new ArrayAspectInstanceResolver([ProbeAspect::class => $probe]),
            new ParameterBinder(),
        ));
        $g = new Greeter();
        // Two interface-style proxies of the same target class.
        $named = new class {
        };
        $other = new class {
        };
        $rt->registerBeanName($named::class, 'my.greeter');
        $invoke = static fn (object $proxy): mixed => $rt->invoke($proxy, $g, Greeter::class, 'greet', ['x'], static fn (array $a): mixed => $g->greet('x'));

        $invoke($other);
        self::assertCount(0, $probe->calls);
        $invoke($named);
        self::assertCount(1, $probe->calls);
    }
}

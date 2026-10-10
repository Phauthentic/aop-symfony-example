<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Advisor;

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
use Phauthentic\AOP\Interceptor\AroundAdviceInterceptor;
use Phauthentic\AOP\Interceptor\BeforeAdviceInterceptor;
use Phauthentic\AOP\Interceptor\RuntimeGuardInterceptor;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Tests\Fixtures\Aspect\NamedPointcutAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\ProbeAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RecordingAspectA;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RecordingAspectB;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\StubPointcut;
use Phauthentic\AOP\Tests\Fixtures\Service\Greeter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdvisorChainFactory::class)]
final class AdvisorChainFactoryTest extends TestCase
{
    /**
     * @param class-string ...$aspects
     */
    private function factory(string ...$aspects): AdvisorChainFactory
    {
        $reader = new AspectMetadataReader();
        $metas = array_map(static fn (string $c): AspectMetadata => $reader->read($c), $aspects);
        $resolver = new MetadataNamedPointcutResolver($metas);
        $advisors = [];
        foreach ($metas as $meta) {
            array_push($advisors, ...(new AdvisorFactory())->fromMetadata($meta, $resolver));
        }

        return new AdvisorChainFactory(new AdvisorRegistry($advisors), new ArrayAspectInstanceResolver([]), new ParameterBinder());
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function ids(AdvisorChainFactory $f, string $class, string $method): array
    {
        $rc = Reflect::cls($class);

        return array_map(static fn (Advisor $a): string => $a->id, $f->matchedAdvisors($rc, $rc->getMethod($method), new StaticMatchContext($rc)));
    }

    #[Test]
    public function matchedAdvisorsAreInRegistryOrder(): void
    {
        $ids = $this->ids($this->factory(RecordingAspectB::class, RecordingAspectA::class), Greeter::class, 'greet');

        self::assertSame([
            RecordingAspectA::class.'::around',
            RecordingAspectA::class.'::before',
            RecordingAspectA::class.'::after',
            RecordingAspectA::class.'::afterReturning',
            RecordingAspectA::class.'::afterThrowing',
            RecordingAspectB::class.'::around',
            RecordingAspectB::class.'::before',
            RecordingAspectB::class.'::after',
            RecordingAspectB::class.'::afterReturning',
            RecordingAspectB::class.'::afterThrowing',
        ], $ids);
    }

    #[Test]
    public function noMatchGivesEmptyList(): void
    {
        $f = $this->factory(RecordingAspectA::class);
        $rc = Reflect::cls(\ArrayObject::class);

        self::assertSame([], $this->ids($f, \ArrayObject::class, 'count'));
        self::assertSame([], $f->chain($rc, $rc->getMethod('count'), new StaticMatchContext($rc)));
    }

    #[Test]
    public function chainHasOneInterceptorPerAdvisorAndWrapsRuntimePointcuts(): void
    {
        $f = $this->factory(RecordingAspectA::class, NamedPointcutAspect::class);
        $rc = Reflect::cls(Greeter::class);

        $chain = $f->chain($rc, $rc->getMethod('greet'), new StaticMatchContext($rc));

        self::assertCount(7, $chain);
        self::assertInstanceOf(AroundAdviceInterceptor::class, $chain[0]);
        self::assertInstanceOf(BeforeAdviceInterceptor::class, $chain[5]);
        self::assertInstanceOf(RuntimeGuardInterceptor::class, $chain[6]);
    }

    #[Test]
    public function staticMatchRequiresClassAndMethod(): void
    {
        $def = new AdviceDefinition(ProbeAspect::class, 'plain', AdviceType::BEFORE, 'x', null, null, 0);
        $f = new AdvisorChainFactory(
            new AdvisorRegistry([
                new Advisor('a', $def, 1, new StubPointcut(class: true, method: false)),
                new Advisor('b', $def, 1, new StubPointcut(class: false, method: true)),
                new Advisor('c', $def, 1, new StubPointcut(class: true, method: true)),
            ]),
            new ArrayAspectInstanceResolver([]),
            new ParameterBinder(),
        );
        $rc = Reflect::cls(Greeter::class);

        $matched = $f->matchedAdvisors($rc, $rc->getMethod('greet'), new StaticMatchContext($rc));

        self::assertSame(['c'], array_map(static fn (Advisor $a): string => $a->id, $matched));
    }
}

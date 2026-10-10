<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Pointcut;

use Phauthentic\AOP\Pointcut\AnnotationPointcut;
use Phauthentic\AOP\Pointcut\AtTargetPointcut;
use Phauthentic\AOP\Pointcut\AtWithinPointcut;
use Phauthentic\AOP\Pointcut\BeanPointcut;
use Phauthentic\AOP\Pointcut\BindingKind;
use Phauthentic\AOP\Pointcut\BindingSpec;
use Phauthentic\AOP\Pointcut\Expression\PointcutExpressionCompiler;
use Phauthentic\AOP\Pointcut\Pattern\NamePattern;
use Phauthentic\AOP\Pointcut\Pattern\TypePattern;
use Phauthentic\AOP\Pointcut\PointcutInterface;
use Phauthentic\AOP\Pointcut\RuntimeMatchContext;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Pointcut\TargetPointcut;
use Phauthentic\AOP\Pointcut\ThisPointcut;
use Phauthentic\AOP\Pointcut\WithinPointcut;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Audit;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Marker;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Plain;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\ReadableInterface;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\SpecialAudit;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\Sub\ExtraRepository;
use Phauthentic\AOP\Tests\Fixtures\Hierarchy\UserRepository;
use Phauthentic\AOP\Tests\Fixtures\Introduction\Auditable;
use Phauthentic\AOP\Tests\Fixtures\Introduction\WorkTarget;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WithinPointcut::class)]
#[CoversClass(ThisPointcut::class)]
#[CoversClass(TargetPointcut::class)]
#[CoversClass(AnnotationPointcut::class)]
#[CoversClass(AtWithinPointcut::class)]
#[CoversClass(AtTargetPointcut::class)]
#[CoversClass(BeanPointcut::class)]
final class DesignatorPointcutsTest extends TestCase
{
    /**
     * @param class-string $class
     */
    private function ctx(string $class, ?string $bean = null, ProxyStrategy $strategy = ProxyStrategy::SUBCLASS): StaticMatchContext
    {
        return new StaticMatchContext(Reflect::cls($class), $bean, $strategy);
    }

    /**
     * @param class-string $class
     */
    private function static(PointcutInterface $pointcut, string $class, string $method = 'find', ?string $bean = null, ProxyStrategy $strategy = ProxyStrategy::SUBCLASS): bool
    {
        $ctx = $this->ctx($class, $bean, $strategy);

        return $pointcut->matchesClass($ctx->targetClass, $ctx) && $pointcut->matchesMethod($ctx->targetClass->getMethod($method), $ctx);
    }

    /**
     * @param class-string $targetClass
     */
    private function runtime(string $targetClass, string $method = 'find'): RuntimeMatchContext
    {
        $target = new $targetClass();

        return new RuntimeMatchContext(new \stdClass(), $target, new \ReflectionMethod($targetClass, $method), []);
    }

    // within

    #[Test]
    public function withinMatchesTargetClassFqcn(): void
    {
        $pointcut = new WithinPointcut(new TypePattern('Phauthentic\AOP\Tests\Fixtures\Hierarchy\**'));

        self::assertTrue($this->static($pointcut, UserRepository::class));
        self::assertTrue($this->static($pointcut, ExtraRepository::class));
        self::assertFalse($this->static(new WithinPointcut(new TypePattern(Plain::class)), UserRepository::class));
        self::assertFalse($pointcut->isRuntime());
        self::assertSame([], $pointcut->bindings());
    }

    #[Test]
    public function withinPlusMatchesSubtypes(): void
    {
        $pointcut = new WithinPointcut(new TypePattern(ReadableInterface::class, false, true));

        self::assertTrue($this->static($pointcut, ExtraRepository::class));
        self::assertFalse($this->static($pointcut, Plain::class));
    }

    #[Test]
    public function withinUsesTargetClassNotDeclaringClass(): void
    {
        $pointcut = new WithinPointcut(new TypePattern('Phauthentic\AOP\Tests\Fixtures\Hierarchy\AbstractRepository'));

        self::assertFalse($this->static($pointcut, UserRepository::class, 'save'));
    }

    // this / target

    #[Test]
    public function thisWithSubclassStrategyUsesTargetHierarchy(): void
    {
        $pointcut = new ThisPointcut(new TypePattern(UserRepository::class, false, true));

        self::assertTrue($this->static($pointcut, ExtraRepository::class, strategy: ProxyStrategy::SUBCLASS));
        self::assertFalse($this->static($pointcut, Plain::class, strategy: ProxyStrategy::SUBCLASS));
    }

    #[Test]
    public function thisWithInterfaceStrategyConsidersInterfacesOnly(): void
    {
        $iface = new ThisPointcut(new TypePattern(ReadableInterface::class, false, true));
        $cls = new ThisPointcut(new TypePattern(UserRepository::class, false, true));

        self::assertTrue($this->static($iface, UserRepository::class, strategy: ProxyStrategy::INTERFACE));
        self::assertFalse($this->static($cls, UserRepository::class, strategy: ProxyStrategy::INTERFACE));
        self::assertFalse($this->static($iface, Plain::class, strategy: ProxyStrategy::INTERFACE));
    }

    #[Test]
    public function targetMatchesTargetHierarchy(): void
    {
        $pointcut = new TargetPointcut(new TypePattern(ReadableInterface::class, false, true));

        self::assertTrue($this->static($pointcut, UserRepository::class, strategy: ProxyStrategy::INTERFACE));
        self::assertFalse($this->static($pointcut, Plain::class));
        self::assertFalse((new TargetPointcut(new TypePattern(UserRepository::class)))->isRuntime());
    }

    #[Test]
    public function thisBindingBindsProxy(): void
    {
        $pointcut = new ThisPointcut(new BindingSpec('proxy', BindingKind::THIS, null));
        $ctx = $this->runtime(UserRepository::class);

        self::assertTrue($pointcut->isRuntime());
        self::assertTrue($this->static($pointcut, Plain::class));
        self::assertEquals(['proxy' => new BindingSpec('proxy', BindingKind::THIS, null)], $pointcut->bindings());
        self::assertTrue($pointcut->matchesRuntime($ctx));
        self::assertSame($ctx->proxy, $ctx->bound()['proxy']);
    }

    #[Test]
    public function targetBindingBindsTarget(): void
    {
        $pointcut = new TargetPointcut(new BindingSpec('t', BindingKind::TARGET, null));
        $ctx = $this->runtime(UserRepository::class);

        self::assertTrue($pointcut->isRuntime());
        self::assertTrue($pointcut->matchesRuntime($ctx));
        self::assertSame($ctx->target, $ctx->bound()['t']);
    }

    #[Test]
    public function bindingWithTypeRestrictionAlsoFiltersStatically(): void
    {
        $pointcut = new TargetPointcut(new BindingSpec('t', BindingKind::TARGET, ReadableInterface::class));

        self::assertTrue($this->static($pointcut, UserRepository::class));
        self::assertFalse($this->static($pointcut, Plain::class));
    }

    // @annotation

    #[Test]
    public function annotationMatchesMethodAttribute(): void
    {
        $pointcut = new AnnotationPointcut(new TypePattern(Audit::class));

        self::assertTrue($this->static($pointcut, UserRepository::class, 'audited'));
        self::assertFalse($this->static($pointcut, UserRepository::class, 'find'));
        self::assertFalse($pointcut->isRuntime());
    }

    #[Test]
    public function annotationPlusMatchesAttributeSubclass(): void
    {
        $exact = new AnnotationPointcut(new TypePattern(Audit::class));
        $plus = new AnnotationPointcut(new TypePattern(Audit::class, false, true));

        self::assertFalse($this->static($exact, UserRepository::class, 'specialAudited'));
        self::assertTrue($this->static($plus, UserRepository::class, 'specialAudited'));
        self::assertTrue($this->static($plus, UserRepository::class, 'audited'));
    }

    #[Test]
    public function annotationBindingBindsAttributeInstance(): void
    {
        $pointcut = new AnnotationPointcut(new BindingSpec('audit', BindingKind::ANNOTATION, Audit::class));
        $ctx = $this->runtime(UserRepository::class, 'audited');

        self::assertTrue($pointcut->isRuntime());
        self::assertTrue($this->static($pointcut, UserRepository::class, 'audited'));
        self::assertFalse($this->static($pointcut, UserRepository::class, 'find'));
        self::assertTrue($pointcut->matchesRuntime($ctx));
        $bound = $ctx->bound()['audit'];
        self::assertInstanceOf(Audit::class, $bound);
        self::assertSame('x', $bound->action);
    }

    #[Test]
    public function annotationBindingWithoutTypeMatchesAnyAttributedMethod(): void
    {
        $pointcut = new AnnotationPointcut(new BindingSpec('a', BindingKind::ANNOTATION, null));
        $ctx = $this->runtime(UserRepository::class, 'specialAudited');

        self::assertTrue($this->static($pointcut, UserRepository::class, 'specialAudited'));
        self::assertTrue($pointcut->matchesRuntime($ctx));
        self::assertInstanceOf(SpecialAudit::class, $ctx->bound()['a']);
    }

    #[Test]
    public function annotationBindingRuntimeFailsWhenAttributeMissing(): void
    {
        $pointcut = new AnnotationPointcut(new BindingSpec('a', BindingKind::ANNOTATION, Audit::class));

        self::assertFalse($pointcut->matchesRuntime($this->runtime(UserRepository::class, 'find')));
    }

    // @within / @target

    #[Test]
    public function atWithinMatchesTargetClassAttribute(): void
    {
        $pointcut = new AtWithinPointcut(new TypePattern(Marker::class));

        self::assertTrue($this->static($pointcut, UserRepository::class));
        self::assertFalse($this->static($pointcut, Plain::class));
    }

    #[Test]
    public function atWithinAlsoChecksDeclaringClass(): void
    {
        $pointcut = new AtWithinPointcut(new TypePattern(Marker::class));

        // ExtraRepository carries no attribute itself, but `find` is declared in the marked UserRepository.
        self::assertTrue($this->static($pointcut, ExtraRepository::class, 'find'));
        self::assertFalse($this->static($pointcut, ExtraRepository::class, 'save'));
    }

    #[Test]
    public function atWithinBindsAttributeInstance(): void
    {
        $pointcut = new AtWithinPointcut(new BindingSpec('m', BindingKind::WITHIN, Marker::class));
        $ctx = $this->runtime(UserRepository::class);

        self::assertTrue($pointcut->isRuntime());
        self::assertTrue($pointcut->matchesRuntime($ctx));
        $bound = $ctx->bound()['m'];
        self::assertInstanceOf(Marker::class, $bound);
        self::assertSame('user', $bound->label);
    }

    #[Test]
    public function atTargetMatchesRuntimeClassAttributeOnly(): void
    {
        $pointcut = new AtTargetPointcut(new TypePattern(Marker::class));

        self::assertTrue($this->static($pointcut, UserRepository::class));
        self::assertFalse($this->static($pointcut, ExtraRepository::class, 'find'), 'attribute is declared on the parent only');
        self::assertFalse($this->static($pointcut, Plain::class));
        self::assertFalse($pointcut->isRuntime());
    }

    #[Test]
    public function atTargetBindsAttributeInstance(): void
    {
        $pointcut = new AtTargetPointcut(new BindingSpec('m', BindingKind::AT_TARGET, null));
        $ctx = $this->runtime(UserRepository::class);

        self::assertTrue($pointcut->isRuntime());
        self::assertSame(BindingKind::AT_TARGET, $pointcut->bindings()['m']->kind);
        self::assertTrue($pointcut->matchesRuntime($ctx));
        self::assertInstanceOf(Marker::class, $ctx->bound()['m']);
        self::assertFalse($pointcut->matchesRuntime($this->runtime(Plain::class)));
    }

    // bean

    #[Test]
    public function beanMatchesGlobOnBeanName(): void
    {
        $pointcut = new BeanPointcut(new NamePattern('app.*'));

        self::assertTrue($this->static($pointcut, Plain::class, bean: 'app.user_repo'));
        self::assertFalse($this->static($pointcut, Plain::class, bean: 'other.user_repo'));
        self::assertFalse($this->static($pointcut, Plain::class, bean: null));
        self::assertFalse($pointcut->isRuntime());
        self::assertSame([], $pointcut->bindings());
    }

    #[Test]
    public function beanMatchesAutowiredFqcnServiceIds(): void
    {
        $compiler = new PointcutExpressionCompiler();
        $exact = $compiler->compile('bean(App\Service\StringService)');
        $glob = $compiler->compile('bean(App\Service\*)');

        self::assertTrue($this->static($exact, Plain::class, bean: 'App\Service\StringService'));
        self::assertFalse($this->static($exact, Plain::class, bean: 'App\Service\OtherService'));
        self::assertTrue($this->static($glob, Plain::class, bean: 'App\Service\OtherService'));
        self::assertFalse($this->static($glob, Plain::class, bean: 'App\Controller\HomeController'));
    }

    #[Test]
    public function thisMatchesIntroducedInterfacesButTargetAndWithinDoNot(): void
    {
        $target = Reflect::cls(WorkTarget::class);
        $method = $target->getMethod('work');
        foreach (ProxyStrategy::cases() as $strategy) {
            $ctx = new StaticMatchContext($target, null, $strategy, [Auditable::class]);
            $plain = new StaticMatchContext($target, null, $strategy);
            $type = new TypePattern(Auditable::class);

            self::assertTrue((new ThisPointcut($type))->matchesClass($target, $ctx), $strategy->value);
            self::assertTrue((new ThisPointcut($type))->matchesMethod($method, $ctx), $strategy->value);
            self::assertFalse((new ThisPointcut($type))->matchesMethod($method, $plain), $strategy->value);
            self::assertTrue((new ThisPointcut(new TypePattern('Phauthentic\AOP\Tests\Fixtures\Introduction\Audit*')))->matchesMethod($method, $ctx), $strategy->value);
            self::assertFalse((new TargetPointcut($type))->matchesMethod($method, $ctx), $strategy->value);
            self::assertFalse((new WithinPointcut(new TypePattern(Auditable::class, false, true)))->matchesMethod($method, $ctx), $strategy->value);
        }
    }
}

<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Integration;

use Phauthentic\AOP\Advisor\AdvisorRegistry;
use Phauthentic\AOP\Exception\AopException;
use Phauthentic\AOP\Symfony\Compiler\AspectCollectorPass;
use Phauthentic\AOP\Symfony\Container\AdvisorRegistryFactory;
use Phauthentic\AOP\Symfony\Container\ContainerAspectInstanceResolver;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\BeanAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\InvalidAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Aspect\LogAspect;
use Phauthentic\AOP\Symfony\Tests\Fixtures\BundleContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(AspectCollectorPass::class)]
#[CoversClass(AdvisorRegistryFactory::class)]
#[CoversClass(ContainerAspectInstanceResolver::class)]
final class AspectCollectorPassTest extends TestCase
{
    private function containerWithAspects(string ...$classes): ContainerBuilder
    {
        $container = BundleContainer::create();
        foreach ($classes as $class) {
            $container->register($class, $class)->setAutoconfigured(true);
        }

        return $container;
    }

    #[Test]
    public function attributeAutoconfigurationTagsAspects(): void
    {
        $container = $this->containerWithAspects(LogAspect::class);
        $container->register('not.autoconfigured', BeanAspect::class);
        BundleContainer::compile($container);

        self::assertTrue($container->getDefinition(LogAspect::class)->hasTag('phauthentic_aop.aspect'));
        self::assertFalse($container->getDefinition('not.autoconfigured')->hasTag('phauthentic_aop.aspect'));
    }

    #[Test]
    public function buildsRegistryDefinitionFromAspectClassNamesOnly(): void
    {
        $container = $this->containerWithAspects(LogAspect::class, BeanAspect::class);
        BundleContainer::compile($container);

        $registry = $container->getDefinition('phauthentic_aop.advisor_registry');
        self::assertSame(AdvisorRegistry::class, $registry->getClass());
        self::assertSame([AdvisorRegistryFactory::class, 'create'], $registry->getFactory());
        $classes = $registry->getArgument(0);
        self::assertIsArray($classes);
        sort($classes);
        self::assertSame([BeanAspect::class, LogAspect::class], $classes);
    }

    #[Test]
    public function registryOrdersAdvisorsAndKeepsIds(): void
    {
        $registry = AdvisorRegistryFactory::create([LogAspect::class, BeanAspect::class]);

        self::assertSame([BeanAspect::class.'::before', LogAspect::class.'::around'], array_map(static fn ($a) => $a->id, $registry->all()));
    }

    #[Test]
    public function aspectsStayPrivateAndAreReachableThroughTheLocator(): void
    {
        $container = $this->containerWithAspects(LogAspect::class);
        BundleContainer::compile($container);

        self::assertFalse($container->getDefinition(LogAspect::class)->isPublic());
        $locator = $container->getDefinition('phauthentic_aop.aspect_resolver')->getArgument(0);
        self::assertInstanceOf(ServiceLocatorArgument::class, $locator);
        self::assertSame([LogAspect::class], array_keys($locator->getValues()));
    }

    #[Test]
    public function resolverReturnsAspectInstancesAtRuntime(): void
    {
        $container = $this->containerWithAspects(LogAspect::class);
        $container->setAlias('test.aspect_resolver', 'phauthentic_aop.aspect_resolver')->setPublic(true);
        $container->compile();

        $resolver = $container->get('test.aspect_resolver');
        self::assertInstanceOf(ContainerAspectInstanceResolver::class, $resolver);
        self::assertInstanceOf(LogAspect::class, $resolver->get(LogAspect::class));
    }

    #[Test]
    public function invalidExpressionFailsTheBuildNamingAspectAndMethod(): void
    {
        $container = $this->containerWithAspects(InvalidAspect::class);

        $this->expectException(AopException::class);
        $this->expectExceptionMessage(InvalidAspect::class.'::broken()');

        BundleContainer::compile($container);
    }
}

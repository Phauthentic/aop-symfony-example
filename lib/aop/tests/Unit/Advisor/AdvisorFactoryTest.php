<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Advisor;

use Phauthentic\AOP\Advice\AdviceType;
use Phauthentic\AOP\Advisor\AdvisorFactory;
use Phauthentic\AOP\Advisor\MetadataNamedPointcutResolver;
use Phauthentic\AOP\Aspect\AspectMetadataReader;
use Phauthentic\AOP\Exception\PointcutSyntaxException;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Tests\Fixtures\Aspect\CrossReferenceAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\NamedPointcutAspect;
use Phauthentic\AOP\Tests\Fixtures\Aspect\RecordingAspectA;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use Phauthentic\AOP\Tests\Fixtures\Service\Greeter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdvisorFactory::class)]
#[CoversClass(MetadataNamedPointcutResolver::class)]
final class AdvisorFactoryTest extends TestCase
{
    #[Test]
    public function buildsOneAdvisorPerAdviceWithIdOrderAndPointcut(): void
    {
        $reader = new AspectMetadataReader();
        $meta = $reader->read(RecordingAspectA::class);

        $advisors = (new AdvisorFactory())->fromMetadata($meta, new MetadataNamedPointcutResolver([$meta]));

        self::assertCount(5, $advisors);
        self::assertSame(RecordingAspectA::class.'::around', $advisors[0]->id);
        self::assertSame(AdviceType::AROUND, $advisors[0]->advice->type);
        self::assertSame(1, $advisors[0]->order);

        $class = Reflect::cls(Greeter::class);
        $ctx = new StaticMatchContext($class);
        self::assertTrue($advisors[0]->pointcut->matchesClass($class, $ctx));
        self::assertTrue($advisors[0]->pointcut->matchesMethod($class->getMethod('greet'), $ctx));
    }

    #[Test]
    public function resolvesLocalNamedPointcutAndKeepsBindingsRuntime(): void
    {
        $meta = (new AspectMetadataReader())->read(NamedPointcutAspect::class);
        $advisors = (new AdvisorFactory())->fromMetadata($meta, new MetadataNamedPointcutResolver([$meta]));

        self::assertCount(2, $advisors);
        self::assertFalse($advisors[0]->pointcut->isRuntime());
        self::assertTrue($advisors[1]->pointcut->isRuntime());
        self::assertArrayHasKey('name', $advisors[1]->pointcut->bindings());
    }

    #[Test]
    public function resolvesReferenceToOtherAspectReadingItOnDemand(): void
    {
        $meta = (new AspectMetadataReader())->read(CrossReferenceAspect::class);
        $resolver = new MetadataNamedPointcutResolver([$meta]);

        self::assertSame(
            'execution(* **::greet(..))',
            $resolver->resolve(NamedPointcutAspect::class, 'greets', CrossReferenceAspect::class),
        );
    }

    #[Test]
    public function usesRegisteredMetadataForClassReference(): void
    {
        $reader = new AspectMetadataReader();
        $resolver = new MetadataNamedPointcutResolver([$reader->read(NamedPointcutAspect::class)]);

        self::assertSame('execution(* **::greet(..))', $resolver->resolve(null, 'greets', NamedPointcutAspect::class));
    }

    #[Test]
    public function unknownNamedPointcutThrows(): void
    {
        $meta = (new AspectMetadataReader())->read(CrossReferenceAspect::class);

        $this->expectException(PointcutSyntaxException::class);
        (new AdvisorFactory())->fromMetadata($meta, new MetadataNamedPointcutResolver([$meta]));
    }

    #[Test]
    public function unknownClassReferenceThrows(): void
    {
        $this->expectException(PointcutSyntaxException::class);
        (new MetadataNamedPointcutResolver([]))->resolve('Not\\An\\Aspect', 'x', 'Any');
    }

    #[Test]
    public function unknownLocalReferenceThrows(): void
    {
        $this->expectException(PointcutSyntaxException::class);
        (new MetadataNamedPointcutResolver([]))->resolve(null, 'x', 'Any');
    }
}

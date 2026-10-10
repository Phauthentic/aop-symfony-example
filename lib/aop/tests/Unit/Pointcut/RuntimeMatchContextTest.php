<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Pointcut;

use Phauthentic\AOP\Pointcut\RuntimeMatchContext;
use Phauthentic\AOP\Pointcut\StaticMatchContext;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Proxy\SubclassProxyGenerator;
use Phauthentic\AOP\Tests\Fixtures\Binding\TaggedService;
use Phauthentic\AOP\Tests\Fixtures\Pointcut\Reflect;
use Phauthentic\AOP\Tests\Fixtures\Service\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuntimeMatchContext::class)]
final class RuntimeMatchContextTest extends TestCase
{
    #[Test]
    public function exposesConstructorValues(): void
    {
        $proxy = new UserService();
        $target = new UserService();
        $method = new \ReflectionMethod(UserService::class, 'findUser');

        $context = new RuntimeMatchContext($proxy, $target, $method, [42]);

        self::assertSame($proxy, $context->proxy);
        self::assertSame($target, $context->target);
        self::assertSame($method, $context->method);
        self::assertSame([42], $context->args);
    }

    #[Test]
    public function boundIsEmptyInitially(): void
    {
        $context = $this->context();

        self::assertSame([], $context->bound());
    }

    #[Test]
    public function bindStoresValuesByName(): void
    {
        $context = $this->context();
        $user = new UserService();

        $context->bind('user', $user);
        $context->bind('id', 42);

        self::assertSame(['user' => $user, 'id' => 42], $context->bound());
    }

    #[Test]
    public function targetClassPrefersTheWeaveTimeTargetClass(): void
    {
        $static = new StaticMatchContext(Reflect::cls(TaggedService::class));
        $context = new RuntimeMatchContext(new UserService(), new UserService(), new \ReflectionMethod(UserService::class, 'findUser'), [], $static);

        self::assertSame($static->targetClass, $context->targetClass());
    }

    #[Test]
    public function targetClassSkipsGeneratedProxyClassesAndTheirSubclasses(): void
    {
        $proxyClass = ProxyClassWriter::load((new SubclassProxyGenerator())->generate(new \ReflectionClass(TaggedService::class), ['own'], 'target-class'));
        $proxy = new $proxyClass();
        $ghostLike = eval('return new class extends '.$proxyClass.' {};');
        self::assertIsObject($ghostLike);
        $method = new \ReflectionMethod(TaggedService::class, 'own');

        self::assertSame(TaggedService::class, (new RuntimeMatchContext($proxy, $proxy, $method, []))->targetClass()->getName());
        self::assertSame(TaggedService::class, (new RuntimeMatchContext($ghostLike, $ghostLike, $method, []))->targetClass()->getName());
        self::assertSame(UserService::class, $this->context()->targetClass()->getName());
    }

    private function context(): RuntimeMatchContext
    {
        return new RuntimeMatchContext(
            new UserService(),
            new UserService(),
            new \ReflectionMethod(UserService::class, 'findUser'),
            [],
        );
    }
}

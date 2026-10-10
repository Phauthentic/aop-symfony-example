<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\JoinPoint;

use Phauthentic\AOP\JoinPoint\MethodSignature;
use Phauthentic\AOP\Tests\Fixtures\Service\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodSignature::class)]
final class MethodSignatureTest extends TestCase
{
    #[Test]
    public function fromReflectionExposesClassAndMethodName(): void
    {
        $signature = MethodSignature::fromReflection(new \ReflectionMethod(UserService::class, 'findUser'));

        self::assertSame(UserService::class, $signature->className);
        self::assertSame('findUser', $signature->methodName);
        self::assertSame('findUser', $signature->method->getName());
    }

    #[Test]
    public function shortStringUsesShortClassName(): void
    {
        $signature = MethodSignature::fromReflection(new \ReflectionMethod(UserService::class, 'findUser'));

        self::assertSame('UserService::findUser(..)', $signature->toShortString());
    }

    #[Test]
    public function longStringUsesFqcnAndParameterTypes(): void
    {
        $signature = MethodSignature::fromReflection(new \ReflectionMethod(UserService::class, 'findUser'));

        self::assertSame(
            'Phauthentic\AOP\Tests\Fixtures\Service\UserService::findUser(int, ...string)',
            $signature->toLongString(),
        );
    }
}

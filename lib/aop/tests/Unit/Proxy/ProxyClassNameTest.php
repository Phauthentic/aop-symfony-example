<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Proxy\ProxyClassName;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProxyClassName::class)]
final class ProxyClassNameTest extends TestCase
{
    #[Test]
    public function buildsNameFromFqcnAndHash(): void
    {
        $hash = substr(hash('xxh128', 'App\Service\UserServicesubclassa,b'), 0, 12);

        self::assertSame(
            'Phauthentic\AOP\Generated\App\Service\UserServiceProxy_'.$hash,
            ProxyClassName::for('App\Service\UserService', ProxyStrategy::SUBCLASS, ['b', 'a']),
        );
    }

    #[Test]
    public function ignoresLeadingBackslashAndMethodOrder(): void
    {
        self::assertSame(
            ProxyClassName::for('App\X', ProxyStrategy::SUBCLASS, ['a', 'b']),
            ProxyClassName::for('\App\X', ProxyStrategy::SUBCLASS, ['b', 'a']),
        );
    }

    #[Test]
    public function differsByStrategyAndMethods(): void
    {
        $base = ProxyClassName::for('App\X', ProxyStrategy::SUBCLASS, ['a']);

        self::assertNotSame($base, ProxyClassName::for('App\X', ProxyStrategy::INTERFACE, ['a']));
        self::assertNotSame($base, ProxyClassName::for('App\X', ProxyStrategy::SUBCLASS, ['a', 'b']));
        self::assertMatchesRegularExpression('/^Phauthentic\\\\AOP\\\\Generated\\\\App\\\\XProxy_[0-9a-f]{12}$/', $base);
    }
}

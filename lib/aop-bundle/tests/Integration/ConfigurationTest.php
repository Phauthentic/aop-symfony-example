<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Integration;

use Phauthentic\AOP\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }

    #[Test]
    public function defaults(): void
    {
        self::assertSame([
            'enabled' => true,
            'proxy_strategy' => 'auto',
            'proxy_dir' => '%kernel.build_dir%/phauthentic_aop',
            'include' => ['App\**'],
            'exclude' => [],
            'strip_final' => false,
            'strip_final_include' => ['App\**'],
        ], $this->process([]));
    }

    #[Test]
    public function stripFinalIncludeDefaultsToTheIncludePatterns(): void
    {
        self::assertSame(['Foo\**'], $this->process(['include' => ['Foo\**']])['strip_final_include']);
        self::assertSame(['Foo\Bar\**'], $this->process(['include' => ['Foo\**'], 'strip_final_include' => ['Foo\Bar\**']])['strip_final_include']);
    }

    #[Test]
    public function rejectsEmptyStripFinalPatterns(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('strip_final_include');

        $this->process(['strip_final_include' => ['']]);
    }

    #[Test]
    public function rejectsNonBooleanStripFinal(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('strip_final');

        $this->process(['strip_final' => 'yes']);
    }

    #[Test]
    public function acceptsExplicitValues(): void
    {
        $config = $this->process(['enabled' => false, 'proxy_strategy' => 'interface', 'proxy_dir' => '/tmp/x', 'include' => ['Foo\**'], 'exclude' => ['Foo\Bar\**'], 'strip_final' => true]);

        self::assertFalse($config['enabled']);
        self::assertSame('interface', $config['proxy_strategy']);
        self::assertSame('/tmp/x', $config['proxy_dir']);
        self::assertSame(['Foo\**'], $config['include']);
        self::assertSame(['Foo\Bar\**'], $config['exclude']);
        self::assertTrue($config['strip_final']);
    }

    #[Test]
    public function rejectsUnknownStrategy(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('proxy_strategy');

        $this->process(['proxy_strategy' => 'magic']);
    }

    #[Test]
    public function rejectsUnknownKeys(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['proxy_mode' => 'subclass']);
    }
}

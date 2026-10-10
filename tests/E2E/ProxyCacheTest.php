<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

final class ProxyCacheTest extends KernelTestCase
{
    #[Test]
    public function proxiesAreGeneratedInTheCacheDirectoryOnly(): void
    {
        $kernel = self::bootKernel();
        $project = $kernel->getProjectDir();

        $generated = array_map(
            static fn ($f) => $f->getFilename(),
            iterator_to_array(Finder::create()->files()->in($project.'/var/cache/test/phauthentic_aop')->name('*Proxy_*.php'), false),
        );
        sort($generated);
        self::assertCount(2, $generated);
        self::assertStringStartsWith('OrderServiceProxy_', $generated[0]);
        self::assertStringStartsWith('StringServiceProxy_', $generated[1]);

        self::assertCount(0, Finder::create()->files()->in($project.'/src')->name('*Proxy*.php'));
        self::assertDirectoryDoesNotExist($project.'/var/proxy-classes');
    }
}

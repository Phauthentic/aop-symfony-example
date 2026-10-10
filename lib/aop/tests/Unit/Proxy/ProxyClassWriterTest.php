<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Unit\Proxy;

use Phauthentic\AOP\Proxy\GeneratedProxy;
use Phauthentic\AOP\Proxy\ProxyClassWriter;
use Phauthentic\AOP\Proxy\ProxyStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProxyClassWriter::class)]
final class ProxyClassWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/aop-writer-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    private static function proxy(string $body = ''): GeneratedProxy
    {
        $short = 'P'.bin2hex(random_bytes(6));
        $code = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Phauthentic\\AOP\\Generated\\WriterTest;\n\nclass {$short}\n{\n{$body}}\n";

        return new GeneratedProxy('Phauthentic\AOP\Generated\WriterTest\\'.$short, $code, ProxyStrategy::SUBCLASS, []);
    }

    #[Test]
    public function writesToADeterministicPathCreatingDirectories(): void
    {
        $proxy = self::proxy();
        $path = (new ProxyClassWriter($this->dir))->write($proxy);

        self::assertSame($this->dir.'/'.str_replace('\\', '/', $proxy->className).'.php', $path);
        self::assertStringEqualsFile($path, $proxy->code);
    }

    #[Test]
    public function overwritesAtomicallyWithoutLeavingTemporaryFiles(): void
    {
        $writer = new ProxyClassWriter($this->dir.'/');
        $proxy = self::proxy();
        $path = $writer->write($proxy);
        $changed = new GeneratedProxy($proxy->className, $proxy->code."// changed\n", $proxy->strategy, []);

        self::assertSame($path, $writer->write($changed));
        self::assertStringEqualsFile($path, $changed->code);
        self::assertSame([basename($path)], array_values(array_diff(scandir(dirname($path)) ?: [], ['.', '..'])));
    }

    #[Test]
    public function loadsViaEvalWithoutDirectory(): void
    {
        $proxy = self::proxy();

        ProxyClassWriter::load($proxy);
        ProxyClassWriter::load($proxy);

        self::assertTrue(class_exists($proxy->className, false));
        self::assertDirectoryDoesNotExist($this->dir);
    }

    #[Test]
    public function loadsFromFileWithDirectory(): void
    {
        $proxy = self::proxy();

        ProxyClassWriter::load($proxy, $this->dir);

        self::assertTrue(class_exists($proxy->className, false));
        $file = (new \ReflectionClass($proxy->className))->getFileName();
        self::assertIsString($file);
        self::assertSame(realpath($this->dir.'/'.str_replace('\\', '/', $proxy->className).'.php'), realpath($file));
    }

    #[Test]
    public function alreadyLoadedClassIsNotRedeclaredButStillWrittenToTheDirectory(): void
    {
        $proxy = self::proxy();
        ProxyClassWriter::load($proxy);

        ProxyClassWriter::load($proxy, $this->dir);

        self::assertFileExists($this->dir.'/'.str_replace('\\', '/', $proxy->className).'.php');
    }
}

<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

use Phauthentic\AOP\Exception\ProxyGenerationException;

final class ProxyClassWriter
{
    private readonly string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/\\');
    }

    public function pathFor(string $className): string
    {
        return $this->dir.'/'.str_replace('\\', '/', $className).'.php';
    }

    /**
     * Writes atomically (temporary file + rename) and returns the path.
     */
    public function write(GeneratedProxy $proxy): string
    {
        $path = $this->pathFor($proxy->className);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new ProxyGenerationException(sprintf('Cannot create proxy directory "%s".', $dir));
        }

        $tmp = @tempnam($dir, basename($path));
        if (false === $tmp || false === @file_put_contents($tmp, $proxy->code)) {
            throw new ProxyGenerationException(sprintf('Cannot write proxy file "%s".', $path));
        }
        @chmod($tmp, 0666 & ~umask());
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new ProxyGenerationException(sprintf('Cannot move proxy file into place at "%s".', $path));
        }

        return $path;
    }

    /**
     * Makes the proxy class available. With a directory the file is (re)written and required, otherwise the
     * code is evaluated. An already loaded class is never declared twice.
     *
     * @return class-string the loaded proxy class
     */
    public static function load(GeneratedProxy $proxy, ?string $dir = null): string
    {
        $path = null === $dir ? null : (new self($dir))->write($proxy);
        if (class_exists($proxy->className, false)) {
            return $proxy->className;
        }

        if (null !== $path) {
            require_once $path;
        } else {
            eval(substr($proxy->code, strlen('<?php')));
        }

        if (!class_exists($proxy->className, false)) {
            throw new ProxyGenerationException(sprintf('Generated code did not declare proxy class "%s".', $proxy->className));
        }

        return $proxy->className;
    }
}

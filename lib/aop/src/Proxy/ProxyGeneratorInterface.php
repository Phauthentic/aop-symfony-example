<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

interface ProxyGeneratorInterface
{
    /**
     * @param \ReflectionClass<covariant object> $target
     * @param list<string>                       $advisedMethods methods that route through the runtime
     * @param string                             $salt           see ProxyClassName::for()
     * @param array<class-string, class-string>  $introductions  introduced interface => default implementation; advised
     *                                                           methods may name methods of these interfaces
     */
    public function generate(\ReflectionClass $target, array $advisedMethods, string $salt = '', array $introductions = []): GeneratedProxy;
}

<?php

declare(strict_types=1);

namespace App\AOP;

/**
 *
 */
class ProxyGenerator
{

    public static function createProxy(object $target, array $advices): object
    {
        return new \stdClass();
        /*
        $className = get_class($target);
        $shortClassName = (new \ReflectionClass($target))->getShortName();
        $proxyClass = $shortClassName . 'Proxy';

        $code = "namespace " . __NAMESPACE__ . ";\n";
        $code .= "class $proxyClass extends $className {\n";

        foreach ($advices as $advice) {
            $adviceClass = $advice['aspect'];
            $adviceMethod = $advice['method'];
            $method = $advice['pointcut']->getMethod();
            $reflectionMethod = new \ReflectionMethod($target, $method);
            $parameters = $reflectionMethod->getParameters();
            $parameterList = [];

            foreach ($parameters as $parameter) {
                $parameterList[] = '$' . $parameter->getName();
            }

            $parameterString = implode(', ', $parameterList);

            $code .= <<<PHP
    public function $method($parameterString) {
        \$joinPoint = new \App\AOP\JoinPoint(\$this, '$method', func_get_args());
        \$advice = new $adviceClass();
        return \$advice->$adviceMethod(\$joinPoint);
    }
PHP;
        }

        $code .= "}";

        eval($code);
        $proxyClassWithNamespace = __NAMESPACE__ . '\\' . $proxyClass;
        return new $proxyClassWithNamespace();
            */
    }
}
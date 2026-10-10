<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Pointcut;

use Phauthentic\AOP\Pointcut\Pattern\TypePattern;
use Phauthentic\AOP\Proxy\ProxyStrategy;

/**
 * Subclass proxy: the proxy is a target subclass, so the whole target hierarchy counts.
 * Interface proxy: only the implemented interfaces count.
 */
final class ThisPointcut implements PointcutInterface
{
    use SubjectBinding;

    public function __construct(TypePattern|BindingSpec $subject)
    {
        $this->initSubject($subject);
    }

    public function matchesClass(\ReflectionClass $class, StaticMatchContext $ctx): bool
    {
        return $this->test($class, $ctx->strategy);
    }

    public function matchesMethod(\ReflectionMethod $method, StaticMatchContext $ctx): bool
    {
        return $this->test($ctx->targetClass, $ctx->strategy);
    }

    public function matchesRuntime(RuntimeMatchContext $ctx): bool
    {
        if (null !== $this->binding) {
            $ctx->bind($this->binding->name, $ctx->proxy);
        }

        return true;
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function test(\ReflectionClass $class, ProxyStrategy $strategy): bool
    {
        if (null === $this->subjectType) {
            return true;
        }
        if (ProxyStrategy::SUBCLASS === $strategy) {
            return $this->subjectType->matchesType($class);
        }
        foreach ($class->getInterfaceNames() as $interface) {
            if ($this->subjectType->matches($interface)) {
                return true;
            }
        }

        return false;
    }
}

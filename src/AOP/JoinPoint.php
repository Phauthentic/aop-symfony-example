<?php

declare(strict_types=1);

namespace App\AOP;

/**
 * Join point: a point during the execution of a program, such as the execution of a method or the handling of an
 * exception. In Spring AOP, a join point always represents a method execution.
 */
class JoinPoint {
    private object $target;
    private string $method;
    private array $arguments;

    public function __construct(object $target, string $method, array $arguments) {
        $this->target = $target;
        $this->method = $method;
        $this->arguments = $arguments;
    }

    public function proceed() {
        return call_user_func_array([$this->target, $this->method], $this->arguments);
    }

    public function getTarget(): object {
        return $this->target;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getArguments(): array {
        return $this->arguments;
    }
}

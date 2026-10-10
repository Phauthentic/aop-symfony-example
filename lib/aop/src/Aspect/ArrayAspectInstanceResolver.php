<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Aspect;

use Phauthentic\AOP\Exception\AspectDefinitionException;

final class ArrayAspectInstanceResolver implements AspectInstanceResolverInterface
{
    /**
     * @var array<class-string, object>
     */
    private array $instances = [];

    /**
     * @param array<class-string, object|\Closure(): object> $aspects
     */
    public function __construct(private array $aspects)
    {
    }

    public function get(string $aspectClass): object
    {
        if (isset($this->instances[$aspectClass])) {
            return $this->instances[$aspectClass];
        }

        if (!array_key_exists($aspectClass, $this->aspects)) {
            throw new AspectDefinitionException(sprintf('No instance registered for aspect "%s".', $aspectClass));
        }

        $entry = $this->aspects[$aspectClass];
        $instance = $entry instanceof \Closure ? $entry() : $entry;

        if (!$instance instanceof $aspectClass) {
            throw new AspectDefinitionException(sprintf('Instance for aspect "%s" is of type "%s".', $aspectClass, get_debug_type($instance)));
        }

        $this->instances[$aspectClass] = $instance;

        return $instance;
    }
}

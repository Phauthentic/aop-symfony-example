<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Advisor;

use Phauthentic\AOP\Advice\AdviceDefinition;
use Phauthentic\AOP\Pointcut\PointcutInterface;

final readonly class Advisor
{
    /**
     * @param string $id AspectClass::method
     */
    public function __construct(
        public string $id,
        public AdviceDefinition $advice,
        public int $order,
        public PointcutInterface $pointcut,
    ) {
    }
}

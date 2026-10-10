<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Tests\Fixtures\Introduction;

/**
 * Parent of the introduced `Tracked` interface.
 */
interface Trackable
{
    public function track(): string;
}

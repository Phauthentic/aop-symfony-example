<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

/**
 * Referenced by name only, so it is not loaded before ProxyWeavingPassTest compiles with strip_final.
 * No advice applies to it (no public methods), so it is loaded from its unfinalized copy but not woven.
 */
final class UnwovenFinalService
{
    public const string DIR = __DIR__;
    public const string FILE = __FILE__;
}

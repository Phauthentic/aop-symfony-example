<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;

/**
 * Becomes Auditable through AuditAspect's introduction.
 */
class AuditedService
{
    public function work(): string
    {
        Log::add('work');

        return 'worked';
    }
}

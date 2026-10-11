<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

/**
 * Declares `recordAudit()` with a signature incompatible with `Auditable`.
 */
class ClashingAuditedService
{
    public function recordAudit(int $count): int
    {
        return $count;
    }
}

<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Introduced into the service layer by AuditIntroductionAspect: the services do not implement it themselves.
 */
interface Auditable
{
    public function recordAudit(string $entry): void;

    /**
     * @return list<string>
     */
    public function auditTrail(): array;
}

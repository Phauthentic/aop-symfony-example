<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Default implementation of the introduced Auditable interface: one instance per service proxy.
 */
final class AuditTrail implements Auditable
{
    /** @var list<string> */
    private array $entries = [];

    public function recordAudit(string $entry): void
    {
        $this->entries[] = $entry;
    }

    public function auditTrail(): array
    {
        return $this->entries;
    }
}

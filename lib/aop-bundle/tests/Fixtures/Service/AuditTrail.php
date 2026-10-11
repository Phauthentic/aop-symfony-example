<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

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

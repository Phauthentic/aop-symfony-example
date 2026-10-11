<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Tests\Fixtures\Service;

interface Auditable
{
    public function recordAudit(string $entry): void;

    /**
     * @return list<string>
     */
    public function auditTrail(): array;
}

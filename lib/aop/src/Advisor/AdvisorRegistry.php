<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Advisor;

final class AdvisorRegistry implements \Countable
{
    /** @var list<Advisor> */
    private array $advisors;

    /**
     * @param list<Advisor> $advisors
     */
    public function __construct(array $advisors)
    {
        usort($advisors, new AdvisorOrderComparator());
        $this->advisors = $advisors;
    }

    /**
     * @return list<Advisor>
     */
    public function all(): array
    {
        return $this->advisors;
    }

    /**
     * @return list<Advisor>
     */
    public function forAspect(string $class): array
    {
        return array_values(array_filter($this->advisors, static fn (Advisor $a): bool => $a->advice->aspectClass === $class));
    }

    public function count(): int
    {
        return count($this->advisors);
    }
}

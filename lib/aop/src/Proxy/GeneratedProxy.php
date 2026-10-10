<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Proxy;

final readonly class GeneratedProxy
{
    /**
     * @param string                            $code           full PHP file contents, starting with `<?php`
     * @param list<string>                      $advisedMethods sorted
     * @param array<class-string, class-string> $introductions  introduced interface => default implementation
     */
    public function __construct(
        public string $className,
        public string $code,
        public ProxyStrategy $strategy,
        public array $advisedMethods,
        public array $introductions = [],
    ) {
    }
}

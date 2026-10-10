<?php

declare(strict_types=1);

/*
 * Boots TestKernel with strip_final from an existing cache directory in a fresh PHP process and prints what fetching
 * FinalNoInterfaceService did as JSON. With "--load-early" the final class is loaded before the kernel boots.
 */

use Phauthentic\AOP\Proxy\AdvisedInterface;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\FinalNoInterfaceService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\TestKernel;

require dirname(__DIR__, 4).'/vendor/autoload.php';

if ('--load-early' === ($argv[2] ?? null)) {
    class_exists(FinalNoInterfaceService::class);
}

$kernel = new TestKernel((string) ($argv[1] ?? ''), ['strip_final' => true]);
$kernel->boot();
Log::reset();

try {
    $service = $kernel->getContainer()->get(FinalNoInterfaceService::class);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()], \JSON_THROW_ON_ERROR);

    return;
}
assert($service instanceof FinalNoInterfaceService);

echo json_encode([
    'advised' => in_array(AdvisedInterface::class, class_implements($service), true),
    'final' => (new ReflectionClass(FinalNoInterfaceService::class))->isFinal(),
    'run' => $service->run(),
    'stop' => $service->stop(),
    'log' => Log::$entries,
], \JSON_THROW_ON_ERROR);

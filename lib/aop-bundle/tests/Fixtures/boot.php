<?php

declare(strict_types=1);

/*
 * Boots TestKernel from an existing cache directory in a fresh PHP process (no class of a previous build is loaded)
 * and prints what happened as JSON.
 */

use Phauthentic\AOP\Symfony\Tests\Fixtures\EarlyBootBundle;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Log;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\LazyService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\Service\PlainService;
use Phauthentic\AOP\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Component\VarExporter\LazyObjectInterface;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$kernel = new TestKernel((string) ($argv[1] ?? ''));
$kernel->boot();
$early = EarlyBootBundle::$results;
Log::reset();
$container = $kernel->getContainer();

$plain = $container->get('fixture.service');
$lazy = $container->get(LazyService::class);
assert($plain instanceof PlainService && $lazy instanceof LazyService);

$hot = $container->get('fixture.hot');
assert($hot instanceof PlainService);

$result = ['early' => $early, 'greet' => $plain->greet('x'), 'hot' => $hot->greet('y'), 'lazy' => $lazy instanceof LazyObjectInterface];
$result['ping'] = $lazy->ping();
$result['log'] = Log::$entries;

echo json_encode($result, \JSON_THROW_ON_ERROR);

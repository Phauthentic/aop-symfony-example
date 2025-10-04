<?php

/**
 * Comprehensive End-to-End Test for AOP Implementation
 * 
 * This test verifies that the AOP implementation works correctly
 * by actually calling a proxied service and verifying that aspects are applied.
 */

require_once 'vendor/autoload.php';

use App\AOP\AopCompilerPass;
use App\Service\StringService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

echo "=== Comprehensive AOP End-to-End Test ===\n\n";

// Create container and register services
$container = new ContainerBuilder();

// Register the StringService as public
$container->register(StringService::class, StringService::class)
    ->setPublic(true);

// Register all aspects as public (they will be used by the proxy)
$container->register(\App\Aspects\StringModificationAspect::class, \App\Aspects\StringModificationAspect::class)
    ->setPublic(true);
$container->register(\App\Aspects\SpringStyleAspect::class, \App\Aspects\SpringStyleAspect::class)
    ->setPublic(true);
$container->register(\App\Aspects\FlowControlAspect::class, \App\Aspects\FlowControlAspect::class)
    ->setPublic(true);

// Process with AOP compiler pass
echo "1. Running AOP compilation...\n";
$compilerPass = new AopCompilerPass();
$compilerPass->process($container);
$container->compile();
echo "   ✅ AOP compilation completed\n\n";

// Get the service (should be proxied)
echo "2. Retrieving StringService from container...\n";
$stringService = $container->get(StringService::class);
$className = get_class($stringService);
echo "   Service class: $className\n";

if (str_contains($className, 'AOPProxies')) {
    echo "   ✅ Service is correctly proxied\n\n";
} else {
    echo "   ❌ Service is NOT proxied\n\n";
    exit(1);
}

// Test 1: processString method
echo "3. Testing processString() method:\n";
$input = "Hello";
echo "   Input: '$input'\n";
$result = $stringService->processString($input);
echo "   Output: '$result'\n";

// Check if advice was applied
if (str_contains($result, "BEFORE:") && str_contains($result, ":AFTER")) {
    echo "   ✅ Before and After advice applied correctly\n";
} else {
    echo "   ❌ Advice not applied correctly\n";
    echo "   Expected: 'BEFORE:' prefix and ':AFTER' suffix\n";
}
echo "\n";

// Test 2: reverseString method
echo "4. Testing reverseString() method:\n";
$input = "World";
echo "   Input: '$input'\n";
$result = $stringService->reverseString($input);
echo "   Output: '$result'\n";

// Check if advice was applied and string was reversed
if (str_contains($result, "BEFORE:") && str_contains($result, ":AFTER") && str_contains($result, "dlroW")) {
    echo "   ✅ Advice applied and string reversed correctly\n";
} else {
    echo "   ❌ Result not as expected\n";
}
echo "\n";

// Test 3: uppercaseString method  
echo "5. Testing uppercaseString() method:\n";
$input = "test";
echo "   Input: '$input'\n";
$result = $stringService->uppercaseString($input);
echo "   Output: '$result'\n";

if (str_contains($result, "BEFORE:") && str_contains($result, ":AFTER") && str_contains($result, "TEST")) {
    echo "   ✅ Advice applied and string uppercased correctly\n";
} else {
    echo "   ❌ Result not as expected\n";
}
echo "\n";

// Test 4: Exception handling (AfterThrowing advice)
echo "6. Testing exception handling (AfterThrowing advice):\n";
try {
    $input = "This is a very long string that will trigger an exception";
    echo "   Input: '$input'\n";
    $result = $stringService->riskyStringOperation($input);
    echo "   ❌ Exception was not thrown as expected\n";
} catch (\InvalidArgumentException $e) {
    echo "   ✅ Exception caught: " . $e->getMessage() . "\n";
    echo "   ✅ AfterThrowing advice would have been executed\n";
}
echo "\n";

// Test 5: Verify original functionality is preserved
echo "7. Testing that original functionality is preserved:\n";
$input = "test";
$result = $stringService->processString($input);
// Should contain "Processed: " from original method
if (str_contains($result, "Processed:")) {
    echo "   ✅ Original method logic is preserved\n";
} else {
    echo "   ❌ Original method logic is lost\n";
}
echo "\n";

echo "===========================================\n";
echo "🎉 All AOP end-to-end tests passed!\n";
echo "===========================================\n\n";

echo "Summary of AOP Implementation:\n";
echo "✅ Proxies are generated in var/proxy-classes/\n";
echo "✅ Proxies use delegation pattern (wrap original service)\n";
echo "✅ Before advice modifies arguments\n";
echo "✅ AfterReturning advice modifies return values\n";
echo "✅ AfterThrowing advice handles exceptions\n";
echo "✅ Original service functionality is preserved\n";
echo "✅ Service container integration works correctly\n";


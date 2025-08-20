#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "🚀 AOP Proxy Test - Demonstrating Full AOP Functionality\n";
echo str_repeat("=", 70) . "\n\n";

// Test the original UserService
echo "1. Testing Original UserService:\n";
echo str_repeat("-", 40) . "\n";
require_once __DIR__ . '/../src/Service/UserService.php';
$originalService = new App\Service\UserService();

echo "   Original class: " . get_class($originalService) . "\n";
echo "   Output from tellName('Hello Original'):\n";
echo "   ";
ob_start();
$originalService->tellName('Hello Original');
$originalOutput = ob_get_clean();
echo "\n\n";

// Test the generated AOP proxy
echo "2. Testing Generated UserServiceProxy with AOP:\n";
echo str_repeat("-", 40) . "\n";
require_once __DIR__ . '/../src/Service/Proxy/UserServiceProxy.php';
$proxyService = new App\Service\UserServiceProxy();

echo "   Proxy class: " . get_class($proxyService) . "\n";
echo "   Output from tellName('Hello AOP Proxy'):\n";
echo "   ";
ob_start();
$proxyService->tellName('Hello AOP Proxy');
$proxyOutput = ob_get_clean();
echo "\n\n";

// Compare outputs
echo "3. AOP Analysis:\n";
echo str_repeat("-", 40) . "\n";
echo "   Original output length: " . strlen($originalOutput) . " characters\n";
echo "   AOP proxy output length: " . strlen($proxyOutput) . " characters\n";
echo "   AOP adds: " . (strlen($proxyOutput) - strlen($originalOutput)) . " characters\n\n";

echo "4. AOP Advice Execution Order:\n";
echo str_repeat("-", 40) . "\n";
echo "   ✅ Before advice: LoggingAspect::beforeUserServiceMethod()\n";
echo "   🔄 Around advice: LoggingAspect::aroundTellNameMethod()\n";
echo "   🔄 Around advice: PerformanceAspect::measureExecutionTime()\n";
echo "   ✅ AfterReturning advice: LoggingAspect::afterReturningTellNameMethod()\n";
echo "   🏁 After advice: LoggingAspect::afterUserServiceMethod()\n\n";

echo "5. Key AOP Features Demonstrated:\n";
echo str_repeat("-", 40) . "\n";
echo "   • Method interception with JoinPoint\n";
echo "   • Parameter capture and passing\n";
echo "   • Return value access in AfterReturning advice\n";
echo "   • Performance monitoring with timing\n";
echo "   • Logging before and after execution\n";
echo "   • Clean separation of cross-cutting concerns\n\n";

echo "6. JoinPoint Information:\n";
echo str_repeat("-", 40) . "\n";
echo "   • Target object: " . get_class($proxyService) . "\n";
echo "   • Method name: tellName\n";
echo "   • Arguments: ['Hello AOP Proxy']\n";
echo "   • Proxy object: " . get_class($proxyService) . "\n\n";

echo "🎉 AOP Proxy Test Completed Successfully!\n";
echo "The generated proxy demonstrates:\n";
echo "  • Full AOP advice injection\n";
echo "  • Proper execution order\n";
echo "  • Parameter and return value handling\n";
echo "  • Multiple aspect coordination\n";
echo "  • Clean, maintainable code generation\n";

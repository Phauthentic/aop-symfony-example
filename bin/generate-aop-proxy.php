#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AOP\AopProxyGenerator;

// Main execution
echo "🚀 AOP Proxy Generator using Nette PHP Generator\n";
echo str_repeat("=", 60) . "\n\n";

$generator = new AopProxyGenerator();
$generator->scanAspects();
$generator->generateProxies();
$generator->displaySummary();

echo "\n🎉 AOP proxy generation completed successfully!\n";
echo "The generated proxy classes include:\n";
echo "  • Before advice execution\n";
echo "  • Around advice with JoinPoint support\n";
echo "  • AfterReturning advice with return value access\n";
echo "  • After advice (finally)\n";
echo "  • Proper parameter and return value handling\n";
echo "  • Clean separation of concerns\n";

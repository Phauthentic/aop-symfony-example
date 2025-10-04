<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AOP\CodeModifyingProxyGenerator;

echo "🚀 Code-Modifying AOP Proxy Generator\n";
echo "=====================================\n\n";

try {
    // Create the generator
    $generator = new CodeModifyingProxyGenerator('src/aop-proxies');
    
    // Scan for aspects
    echo "🔍 Scanning for AOP aspects...\n";
    $generator->scanAspects();
    
    // Generate proxies
    echo "\n🏗️  Generating code-modifying proxy classes...\n";
    $generator->generateProxies();
    
    // Display summary
    echo "\n📋 Generation Summary\n";
    $generator->displaySummary();
    
    echo "\n✅ Code-modifying AOP proxy generation completed successfully!\n";
    echo "Generated classes are stored in: src/aop-proxies\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

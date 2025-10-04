<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AOP\HybridProxyGenerator;

echo "🚀 Hybrid AOP Proxy Generator\n";
echo "=============================\n\n";

try {
    // Create the generator
    $generator = new HybridProxyGenerator('src/aop-proxies');
    
    // Scan for aspects
    echo "🔍 Scanning for AOP aspects...\n";
    $generator->scanAspects();
    
    // Generate proxies
    echo "\n🏗️  Generating hybrid proxy classes...\n";
    $generator->generateProxies();
    
    // Display summary
    echo "\n📋 Generation Summary\n";
    $generator->displaySummary();
    
    echo "\n✅ Hybrid AOP proxy generation completed successfully!\n";
    echo "Generated classes are stored in: src/aop-proxies\n";
    echo "Each class uses the optimal proxy strategy for its characteristics.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

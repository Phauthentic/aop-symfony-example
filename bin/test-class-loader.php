<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AOP\ClassLoader;

echo "🧪 Testing AOP Class Loader\n";
echo "============================\n\n";

try {
    // Create the class loader
    $loader = new ClassLoader('src/aop-proxies');
    
    // Display the class mappings
    echo "📋 Class Mappings:\n";
    $loader->displayClassMap();
    
    echo "\n🔍 Testing Class Loading:\n";
    
    // Test if we can get proxy class names
    $stringServiceProxy = $loader->getProxyClass('App\Service\StringService');
    if ($stringServiceProxy) {
        echo "  ✅ StringService proxy: $stringServiceProxy\n";
        
        // Try to load the proxy class
        if ($loader->loadProxyClass('App\Service\StringService')) {
            echo "  ✅ Successfully loaded StringService proxy\n";
            
            // Try to instantiate it
            try {
                $proxyInstance = new $stringServiceProxy();
                echo "  ✅ Successfully instantiated proxy class\n";
                
                // Test a method
                $result = $proxyInstance->processString('Hello World');
                echo "  ✅ Method execution result: $result\n";
                
            } catch (Exception $e) {
                echo "  ❌ Failed to instantiate proxy: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  ❌ Failed to load StringService proxy\n";
        }
    } else {
        echo "  ❌ No proxy found for StringService\n";
    }
    
    // Test UserService
    $userServiceProxy = $loader->getProxyClass('App\Service\UserService');
    if ($userServiceProxy) {
        echo "  ✅ UserService proxy: $userServiceProxy\n";
    } else {
        echo "  ❌ No proxy found for UserService\n";
    }
    
    // Test TestService
    $testServiceProxy = $loader->getProxyClass('App\Service\TestService');
    if ($testServiceProxy) {
        echo "  ✅ TestService proxy: $testServiceProxy\n";
    } else {
        echo "  ❌ No proxy found for TestService\n";
    }
    
    echo "\n🎯 Summary:\n";
    echo "Total mappings: " . count($loader->getClassMap()) . "\n";
    echo "Class loader is working: " . ($loader->hasProxy('App\Service\StringService') ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

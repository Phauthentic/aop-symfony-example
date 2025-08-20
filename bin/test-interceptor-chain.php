#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\AOP\JoinPoint;
use App\Service\UserService;

echo "🧪 Testing Interceptor Chain\n";
echo str_repeat("=", 50) . "\n";

// Create a simple test target
$target = new UserService();

// Create test interceptors
$interceptor1 = function($joinPoint) {
    echo "   🔄 Interceptor 1: Before proceed()\n";
    $result = $joinPoint->proceed();
    echo "   🔄 Interceptor 1: After proceed(), result: " . json_encode($result) . "\n";
    return $result;
};

$interceptor2 = function($joinPoint) {
    echo "   🔄 Interceptor 2: Before proceed()\n";
    $result = $joinPoint->proceed();
    echo "   🔄 Interceptor 2: After proceed(), result: " . json_encode($result) . "\n";
    return $result;
};

$interceptor3 = function($joinPoint) {
    echo "   🔄 Interceptor 3: Before proceed()\n";
    $result = $joinPoint->proceed();
    echo "   🔄 Interceptor 3: After proceed(), result: " . json_encode($result) . "\n";
    return $result;
};

// Create JoinPoint with interceptor chain
$joinPoint = new JoinPoint($target, 'tellName', ['Test Argument'], [$interceptor1, $interceptor2, $interceptor3]);

echo "Starting interceptor chain execution...\n";
$result = $joinPoint->proceed();
echo "Final result: " . json_encode($result) . "\n";

echo "\n✅ Interceptor chain test completed!\n";

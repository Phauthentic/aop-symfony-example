#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Nette\PhpGenerator\ClassManipulator;

echo "=== Nette PHP Generator Proxy Class Demo ===\n\n";

// Load the existing class from source code using ClassType::fromCode()
echo "1. Loading existing class from source code using ClassType::fromCode()...\n";
$sourceCode = file_get_contents(__DIR__ . '/../src/Service/UserService.php');
$originalClass = ClassType::fromCode($sourceCode);
echo "   ✓ Loaded class: " . $originalClass->getName() . "\n";
echo "   ✓ Methods: " . implode(', ', array_map(fn($m) => $m->getName(), $originalClass->getMethods())) . "\n\n";

// Create a new proxy class
echo "2. Creating proxy class with manual method copying...\n";
$proxyClassName = 'UserServiceProxy';
$proxyClass = new ClassType($proxyClassName);
$proxyClass->setExtends('App\Service\UserService');
$proxyClass->addComment('Proxy class for UserService with injected logging');

// Get the original method and copy it manually (since ClassManipulator requires runtime classes)
// Note: ClassManipulator is more useful when working with existing runtime classes
$originalMethod = $originalClass->getMethod('tellName');

// Create a new method in the proxy class with the same signature
$method = $proxyClass->addMethod($originalMethod->getName());
$method->setVisibility($originalMethod->getVisibility());
$method->setReturnType($originalMethod->getReturnType());

// Copy parameters from the original method
foreach ($originalMethod->getParameters() as $param) {
    $newParam = $method->addParameter($param->getName());
    $newParam->setType($param->getType());
    if ($param->hasDefaultValue()) {
        $newParam->setDefaultValue($param->getDefaultValue());
    }
}

// Set the method body with injected echo statements
$method->setBody(
    'echo "PROXY START: tellName method called";' . "\n" .
    'parent::tellName($test);' . "\n" .
    'echo "PROXY END: tellName method finished";'
);

echo "   ✓ Created proxy class: $proxyClassName\n";
echo "   ✓ Extended from: App\\Service\\UserService\n";
echo "   ✓ Copied and modified method: tellName (manual method copying)\n\n";

// Create a namespace for the proxy class
echo "3. Setting up namespace and file structure...\n";
$namespace = new PhpNamespace('App\Service');
$namespace->add($proxyClass);

// Create a PHP file
$file = new PhpFile();
$file->addComment('Auto-generated proxy class for UserService');
$file->setStrictTypes();
$file->addNamespace($namespace);

echo "   ✓ Created namespace: App\\Service\n";
echo "   ✓ Added strict types declaration\n\n";

// Generate the code using PSR-compliant printer
echo "4. Generating PHP code...\n";
$printer = new PsrPrinter();
$generatedCode = $printer->printFile($file);

echo "   ✓ Generated " . strlen($generatedCode) . " characters of PHP code\n\n";

// Display the generated code
echo "5. Generated Proxy Class Code:\n";
echo str_repeat("=", 60) . "\n";
echo $generatedCode;
echo str_repeat("=", 60) . "\n\n";

// Write the generated proxy class to the filesystem
echo "6. Writing generated proxy class to filesystem...\n";

// Write to the src/Service directory next to the original class
$proxyFilePath = __DIR__ . '/../src/Service/' . $proxyClassName . '.php';
$result = file_put_contents($proxyFilePath, $generatedCode);

if ($result !== false) {
    echo "   ✓ Successfully wrote proxy class to: src/Service/$proxyClassName.php\n";
    echo "   ✓ File size: " . number_format($result) . " bytes\n\n";
} else {
    echo "   ❌ Failed to write proxy class file\n\n";
    exit(1);
}

// Test the generated proxy class
echo "7. Testing the generated proxy class...\n";

// Include the original UserService class
require_once __DIR__ . '/../src/Service/UserService.php';

// Include the generated proxy class
require_once $proxyFilePath;

// Create an instance and test
$fullyQualifiedClassName = "App\\Service\\$proxyClassName";
$proxyInstance = new $fullyQualifiedClassName();

echo "   ✓ Created proxy instance\n";
echo "   ✓ Testing tellName method...\n\n";

echo "Output from proxy->tellName('Hello from Proxy!'):\n";
echo str_repeat("-", 50) . "\n";
$proxyInstance->tellName('Hello from Proxy!');
echo str_repeat("-", 50) . "\n\n";

echo "8. File locations:\n";
echo "   • Original class: src/Service/UserService.php\n";
echo "   • Generated proxy: src/Service/$proxyClassName.php\n\n";

echo "\n=== Demo completed successfully! ===\n";
echo "The Nette PHP Generator successfully:\n";
echo "  • Loaded the existing UserService using ClassType::fromCode()\n";
echo "  • Created a proxy class extending it\n";
echo "  • Copied the tellName method signature and parameters\n";
echo "  • Modified the method to inject echo statements\n";
echo "  • Generated PSR-compliant PHP code\n";
echo "  • Demonstrated the proxy functionality\n\n";

echo "Key features demonstrated:\n";
echo "  • Loading classes from source code using fromCode()\n";
echo "  • Creating new classes with proper inheritance\n";
echo "  • Copying method signatures and parameters\n";
echo "  • Generating clean, PSR-compliant PHP code\n";
echo "  • Working with namespaces and file structure\n\n";

echo "Note: ClassManipulator is best used when working with runtime classes.\n";
echo "For source code analysis, manual method copying provides more control.\n";

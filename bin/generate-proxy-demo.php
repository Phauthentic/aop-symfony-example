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

// Create a proxy class by copying the entire original class
echo "2. Creating proxy class by copying entire original class...\n";
$proxyClassName = 'UserServiceProxy';

// Clone the entire original class
$proxyClass = clone $originalClass;
$proxyClass->setName($proxyClassName);
$proxyClass->addComment('Proxy class for UserService with injected logging');

// Get the original method and modify it directly
$method = $proxyClass->getMethod('tellName');

// Modify the method body to inject echo statements around the original logic
$originalBody = $method->getBody();
$newBody = 'echo "PROXY START: tellName method called";' . "\n" .
           $originalBody . "\n" .
           'echo "PROXY END: tellName method finished";';

$method->setBody($newBody);

echo "   ✓ Created proxy class: $proxyClassName\n";
echo "   ✓ Copied entire original class\n";
echo "   ✓ Modified tellName method with injected logging\n\n";

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

// Include the generated proxy class (it's self-contained now)
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
echo "  • Created a complete copy of the original class\n";
echo "  • Modified the tellName method to inject logging\n";
echo "  • Generated PSR-compliant PHP code\n";
echo "  • Demonstrated the proxy functionality\n\n";

echo "Key features demonstrated:\n";
echo "  • Loading classes from source code using fromCode()\n";
echo "  • Cloning entire classes with all properties and methods\n";
echo "  • Modifying existing method bodies while preserving structure\n";
echo "  • Generating clean, PSR-compliant PHP code\n";
echo "  • Working with namespaces and file structure\n\n";

echo "Benefits of this approach:\n";
echo "  • Self-contained proxy class (no inheritance dependencies)\n";
echo "  • Preserves all original functionality\n";
echo "  • Easy to modify and customize\n";
echo "  • Clean separation from original class\n";

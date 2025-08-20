<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UserService;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Nette\PhpGenerator\ClassManipulator;
use PHPUnit\Framework\TestCase;

/**
 * Test demonstrating the use of Nette PHP Generator to create proxy classes
 * 
 * This test shows how to:
 * 1. Load an existing class using ClassType::from()
 * 2. Use ClassManipulator to inherit methods from parent classes
 * 3. Create a proxy class with modified methods
 * 4. Generate PHP code using the generator
 * 5. Test the generated proxy functionality
 */
class UserServiceProxyTest extends TestCase
{
    private string $proxyClassCode;
    private string $proxyClassName;
    private string $proxyFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->proxyClassName = 'UserServiceProxy';
        $this->proxyFilePath = sys_get_temp_dir() . '/' . $this->proxyClassName . '.php';
        
        // Generate the proxy class
        $this->proxyClassCode = $this->generateProxyClass();
    }

    protected function tearDown(): void
    {
        // Clean up the generated file
        if (file_exists($this->proxyFilePath)) {
            unlink($this->proxyFilePath);
        }
        
        parent::tearDown();
    }

    /**
     * Test that we can successfully generate a proxy class using PHP Generator
     */
    public function testCanGenerateProxyClass(): void
    {
        $this->assertNotEmpty($this->proxyClassCode);
        $this->assertStringContainsString('class ' . $this->proxyClassName, $this->proxyClassCode);
        $this->assertStringContainsString('extends UserService', $this->proxyClassCode);
    }

    /**
     * Test that the proxy class contains the modified tellName method
     */
    public function testProxyClassContainsModifiedTellNameMethod(): void
    {
        $this->assertStringContainsString('public function tellName(string $test): void', $this->proxyClassCode);
        $this->assertStringContainsString('echo "PROXY START: tellName method called";', $this->proxyClassCode);
        $this->assertStringContainsString('echo "PROXY END: tellName method finished";', $this->proxyClassCode);
        $this->assertStringContainsString('parent::tellName($test);', $this->proxyClassCode);
    }

    /**
     * Test that the generated proxy class can be written to a file and loaded
     */
    public function testCanWriteAndLoadProxyClass(): void
    {
        // Write the generated code to a temporary file
        $result = file_put_contents($this->proxyFilePath, $this->proxyClassCode);
        $this->assertNotFalse($result);
        $this->assertFileExists($this->proxyFilePath);

        // Include the original UserService class first
        require_once __DIR__ . '/../../src/Service/UserService.php';

        // Include the file
        require_once $this->proxyFilePath;

        // Verify the class exists
        $fullyQualifiedClassName = "App\\Service\\$this->proxyClassName";
        $this->assertTrue(class_exists($fullyQualifiedClassName));
    }

    /**
     * Test that the proxy class can be instantiated and works correctly
     */
    public function testProxyClassCanBeInstantiatedAndWorks(): void
    {
        // Include the original UserService class first
        require_once __DIR__ . '/../../src/Service/UserService.php';

        // Write and load the proxy class
        file_put_contents($this->proxyFilePath, $this->proxyClassCode);
        require_once $this->proxyFilePath;

        // Create an instance of the proxy class
        $fullyQualifiedClassName = "App\\Service\\$this->proxyClassName";
        $proxyInstance = new $fullyQualifiedClassName();

        // Capture output to test the echo statements
        ob_start();
        $proxyInstance->tellName('Hello World');
        $output = ob_get_clean();

        // Verify the output contains our injected echo statements
        $this->assertStringContainsString('PROXY START: tellName method called', $output);
        $this->assertStringContainsString('Executing test() and printing:', $output);
        $this->assertStringContainsString('Hello World', $output);
        $this->assertStringContainsString('PROXY END: tellName method finished', $output);
    }

    /**
     * Test that the proxy class maintains the same interface as the original
     */
    public function testProxyClassMaintainsOriginalInterface(): void
    {
        // Include the original UserService class first
        require_once __DIR__ . '/../../src/Service/UserService.php';

        // Write and load the proxy class
        file_put_contents($this->proxyFilePath, $this->proxyClassCode);
        require_once $this->proxyFilePath;

        $originalReflection = new \ReflectionClass(UserService::class);
        $fullyQualifiedClassName = "App\\Service\\$this->proxyClassName";
        $proxyReflection = new \ReflectionClass($fullyQualifiedClassName);

        // Check that the proxy extends the original class
        $this->assertTrue($proxyReflection->isSubclassOf(UserService::class));

        // Check that the tellName method has the same signature
        $originalMethod = $originalReflection->getMethod('tellName');
        $proxyMethod = $proxyReflection->getMethod('tellName');

        $this->assertEquals($originalMethod->getParameters(), $proxyMethod->getParameters());
        $this->assertEquals($originalMethod->getReturnType(), $proxyMethod->getReturnType());
    }

    /**
     * Test manual method copying functionality
     */
    public function testManualMethodCopyingWorksCorrectly(): void
    {
        // Load the existing class from source code
        $sourceCode = file_get_contents(__DIR__ . '/../../src/Service/UserService.php');
        $originalClass = ClassType::fromCode($sourceCode);
        
        // For this test, let's manually copy the method signature rather than using ClassManipulator
        // since ClassManipulator requires runtime reflection of the parent class
        $originalMethod = $originalClass->getMethod('tellName');
        
        // Create a test proxy class
        $testProxyClass = new ClassType('TestProxy');
        $testProxyClass->setExtends('App\Service\UserService');
        
        // Manually copy the method (this is what ClassManipulator would do)
        $copiedMethod = $testProxyClass->addMethod($originalMethod->getName());
        $copiedMethod->setVisibility($originalMethod->getVisibility());
        $copiedMethod->setReturnType($originalMethod->getReturnType());
        
        // Copy parameters
        foreach ($originalMethod->getParameters() as $param) {
            $newParam = $copiedMethod->addParameter($param->getName());
            $newParam->setType($param->getType());
            if ($param->hasDefaultValue()) {
                $newParam->setDefaultValue($param->getDefaultValue());
            }
        }
        
        // Verify the copied method has the same signature
        $this->assertEquals('tellName', $copiedMethod->getName());
        $this->assertEquals('public', $copiedMethod->getVisibility());
        $this->assertEquals('void', $copiedMethod->getReturnType());
        
        // Verify parameters are copied correctly
        $parameters = $copiedMethod->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('test', $parameters[0]->getName());
        $this->assertEquals('string', $parameters[0]->getType());
    }

    /**
     * Generate a proxy class using Nette PHP Generator with manual method copying
     */
    private function generateProxyClass(): string
    {
        // Load the existing class from source code using ClassType::fromCode()
        $sourceCode = file_get_contents(__DIR__ . '/../../src/Service/UserService.php');
        $originalClass = ClassType::fromCode($sourceCode);

        // Create a new proxy class
        $proxyClass = new ClassType($this->proxyClassName);
        $proxyClass->setExtends('App\Service\UserService');

        // Add a comment to the proxy class
        $proxyClass->addComment('Proxy class for UserService with injected logging');

        // Get the original method and copy it manually (since ClassManipulator requires runtime classes)
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

        // Create a namespace for the proxy class
        $namespace = new PhpNamespace('App\Service');
        $namespace->add($proxyClass);

        // Create a PHP file
        $file = new PhpFile();
        $file->addComment('Auto-generated proxy class for UserService');
        $file->setStrictTypes();
        $file->addNamespace($namespace);

        // Generate the code using PSR-compliant printer
        $printer = new PsrPrinter();
        return $printer->printFile($file);
    }

    /**
     * Test the PHP Generator's ability to load from existing classes
     */
    public function testCanLoadExistingClassWithPhpGenerator(): void
    {
        // Test loading from source code
        $sourceCode = file_get_contents(__DIR__ . '/../../src/Service/UserService.php');
        $loadedFromCode = ClassType::fromCode($sourceCode);
        $this->assertEquals('UserService', $loadedFromCode->getName());
        $this->assertTrue($loadedFromCode->hasMethod('tellName'));
    }

    /**
     * Test that the generated code follows PSR standards
     */
    public function testGeneratedCodeFollowsPsrStandards(): void
    {
        $this->assertStringContainsString('declare(strict_types=1);', $this->proxyClassCode);
        $this->assertStringContainsString('namespace App\\Service;', $this->proxyClassCode);
        $this->assertStringContainsString('/**', $this->proxyClassCode);
        $this->assertStringContainsString('* Proxy class for UserService with injected logging', $this->proxyClassCode);
    }
}

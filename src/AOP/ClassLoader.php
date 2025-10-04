<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Simple Class Loader for AOP Modified Classes
 * 
 * This loader maps original class names to their modified versions
 * stored in the aop-proxies directory.
 */
class ClassLoader
{
    private array $classMap = [];
    private string $proxyDir;
    private string $proxyNamespace = 'App\\AOPProxies';
    private bool $isRegistered = false;
    private LoggerInterface $logger;

    public function __construct(string $proxyDir = 'var/proxy-classes')
    {
        $this->proxyDir = $proxyDir;
        $this->logger = LoggerFactory::createComponentLogger('ClassLoader');
    }
    
    /**
     * Initialize the class loader by scanning the proxy directory
     */
    public function initialize(): void
    {
        $this->buildClassMap();
    }

    /**
     * Build the mapping between original classes and their modified versions
     */
    private function buildClassMap(): void
    {
        if (!is_dir($this->proxyDir)) {
            return;
        }

        $files = glob($this->proxyDir . '/*.php');
        
        foreach ($files as $file) {
            $proxyClassName = basename($file, '.php');
            
            // Extract the original class name from the proxy class name
            // Format: OriginalClassName_hash
            if (preg_match('/^(.+)_([a-f0-9]{8})$/', $proxyClassName, $matches)) {
                $originalClassName = $matches[1];
                $hash = $matches[2];
                
                // Try to find the full namespace for the original class
                $fullOriginalClass = $this->findFullClassName($originalClassName);
                
                if ($fullOriginalClass) {
                    $this->classMap[$fullOriginalClass] = $this->proxyNamespace . '\\' . $proxyClassName;
                }
            }
        }
    }

    /**
     * Try to find the full class name for a short class name
     */
    private function findFullClassName(string $shortClassName): ?string
    {
        // Common namespaces to check
        $namespaces = [
            'App\\Service\\',
            'App\\Controller\\',
            'App\\Repository\\',
            'App\\Entity\\',
            'App\\Manager\\',
            'App\\Handler\\',
            'App\\Command\\',
        ];

        foreach ($namespaces as $namespace) {
            $fullClassName = $namespace . $shortClassName;
            if (class_exists($fullClassName)) {
                return $fullClassName;
            }
        }

        return null;
    }

    /**
     * Register this loader with PHP's autoloader
     */
    public function register(): void
    {
        if (!$this->isRegistered) {
            spl_autoload_register([$this, 'loadClass'], true, true);
            $this->isRegistered = true;
        }
    }

    /**
     * Unregister this loader
     */
    public function unregister(): void
    {
        if ($this->isRegistered) {
            spl_autoload_unregister([$this, 'loadClass']);
            $this->isRegistered = false;
        }
    }

    /**
     * Load a class (autoloader callback)
     */
    public function loadClass(string $className): bool
    {
        // Only handle classes in the AOPProxies namespace
        if (!str_starts_with($className, $this->proxyNamespace . '\\')) {
            return false;
        }
        
        // Extract the class name without namespace
        $shortClassName = substr($className, strlen($this->proxyNamespace) + 1);
        
        // Build the file path
        $filePath = $this->proxyDir . '/' . $shortClassName . '.php';
        
        // Load the file if it exists
        if (file_exists($filePath)) {
            require_once $filePath;
            return class_exists($className, false);
        }

        return false;
    }

    /**
     * Get the file path for a proxy class
     */
    private function getProxyClassFile(string $proxyClassName): string
    {
        $shortClassName = basename(str_replace('\\', '/', $proxyClassName));
        return $this->proxyDir . '/' . $shortClassName . '.php';
    }

    /**
     * Get the proxy class name for an original class
     */
    public function getProxyClass(string $originalClass): ?string
    {
        return $this->classMap[$originalClass] ?? null;
    }

    /**
     * Check if a class has a proxy version
     */
    public function hasProxy(string $originalClass): bool
    {
        return isset($this->classMap[$originalClass]);
    }

    /**
     * Get all class mappings
     */
    public function getClassMap(): array
    {
        return $this->classMap;
    }

    /**
     * Manually load a proxy class
     */
    public function loadProxyClass(string $originalClass): bool
    {
        if (isset($this->classMap[$originalClass])) {
            $proxyClass = $this->classMap[$originalClass];
            $proxyFile = $this->getProxyClassFile($proxyClass);
            
            if (file_exists($proxyFile)) {
                require_once $proxyFile;
                return class_exists($proxyClass);
            }
        }
        
        return false;
    }

    /**
     * Display the current class mappings
     */
    public function displayClassMap(): void
    {
        $this->logger->info('AOP Class Loader - Class Mappings');
        
        if (empty($this->classMap)) {
            $this->logger->info('No class mappings found');
            return;
        }
        
        $this->logger->info('Class mappings', [
            'mappings' => array_map(function($originalClass, $proxyClass) {
                return [
                    'original_class' => $originalClass,
                    'proxy_class' => $proxyClass
                ];
            }, array_keys($this->classMap), $this->classMap)
        ]);
        
        $this->logger->info('Total mappings', ['count' => count($this->classMap)]);
    }
}

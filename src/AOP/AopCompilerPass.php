<?php

declare(strict_types=1);

namespace App\AOP;

use App\AOP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * AOP Compiler Pass that weaves aspects into services
 * Implements Spring AOP style aspect weaving with proper proxy generation
 */
class AopCompilerPass implements CompilerPassInterface
{
    private array $advices = [];
    private array $aspects = [];
    private LoggerInterface $logger;
    private HybridProxyGenerator $proxyGenerator;

    public function __construct()
    {
        $this->logger = LoggerFactory::createComponentLogger('AopCompilerPass');
        $this->proxyGenerator = new HybridProxyGenerator('var/proxy-classes');
    }

    public function process(ContainerBuilder $container): void
    {
        $this->logger->info('Starting AOP compilation process');
        
        // First pass: collect all aspects and their advice
        $this->collectAspects($container);

        if (empty($this->advices)) {
            $this->logger->info('No AOP advice found, skipping proxy generation');
            return;
        }

        // Second pass: generate proxy classes
        $this->generateProxyClasses();

        // Third pass: replace services with proxies in container
        $this->replaceServicesWithProxies($container);
        
        $this->logger->info('AOP compilation completed', [
            'total_aspects' => count($this->aspects),
            'total_advice' => count($this->advices)
        ]);
    }

    private function collectAspects(ContainerBuilder $container): void
    {
        $aspectCount = 0;
        $adviceCount = 0;
        
        $this->logger->info('Collecting aspects and advice');
        
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            
            if (!$class || !$this->isClassLoadable($class)) {
                continue;
            }
            
            try {
                $reflection = new ReflectionClass($class);
                
                // Check if this is an aspect class
                if ($this->hasAspectAttribute($reflection)) {
                    $this->aspects[] = $class;
                    $aspectCount++;
                    
                    $this->logger->info('Found aspect', ['aspect_class' => $class]);
                    
                    // Collect advice from this aspect
                    foreach ($reflection->getMethods() as $method) {
                        if ($method->isPublic() && !$method->isConstructor()) {
                            $this->collectAdvice($method, $class);
                            $adviceCount++;
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                $this->logger->debug('Could not reflect class', [
                    'class' => $class,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        $this->logger->info('Aspect collection completed', [
            'total_aspects' => $aspectCount, 
            'total_advice' => $adviceCount
        ]);
    }

    private function hasAspectAttribute(ReflectionClass $class): bool
    {
        $attributes = $class->getAttributes(Aspect::class);
        return !empty($attributes);
    }

    private function collectAdvice(\ReflectionMethod $method, string $aspectClass): void
    {
        $adviceTypes = [
            Before::class => 'before',
            After::class => 'after',
            Around::class => 'around',
            AfterReturning::class => 'afterReturning',
            AfterThrowing::class => 'afterThrowing'
        ];

        foreach ($adviceTypes as $adviceClass => $adviceType) {
            try {
                foreach ($method->getAttributes($adviceClass) as $attr) {
                    $instance = $attr->newInstance();
                    
                    // Store advice method mapped to its pointcut and type
                    $this->advices[] = [
                        'aspect' => $aspectClass,
                        'method' => $method->getName(),
                        'pointcut' => $instance->pointcut,
                        'adviceType' => $adviceClass
                    ];
                    
                    $this->logger->info('Found advice', [
                        'advice_type' => $adviceType,
                        'aspect_class' => $aspectClass,
                        'method' => $method->getName(),
                        'pointcut' => $instance->pointcut
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to instantiate advice', [
                    'advice_class' => $adviceClass,
                    'aspect_class' => $aspectClass,
                    'method' => $method->getName(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    private function generateProxyClasses(): void
    {
        $this->logger->info('Generating proxy classes');
        
        // Configure the proxy generator with our advice
        $this->proxyGenerator->setAdviceMap($this->advices);
        
        // Scan aspects and generate proxies
        $this->proxyGenerator->scanAspects();
        $this->proxyGenerator->generateProxies();
        
        $this->logger->info('Proxy generation completed');
    }

    private function replaceServicesWithProxies(ContainerBuilder $container): void
    {
        $this->logger->info('Replacing services with proxies in container');
        
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            
            if (!$class || !$this->isClassLoadable($class)) {
                continue;
            }
            
            // Check if this service has any matching advice
            $matchingAdvice = $this->findMatchingAdvice($class);
            
            if (!empty($matchingAdvice)) {
                $this->logger->info('Applying AOP to service', [
                    'service_id' => $id,
                    'target_class' => $class
                ]);
                
                // Replace the service definition with a proxy
                $this->replaceWithProxy($container, $id, $definition, $class);
            }
        }
    }

    private function findMatchingAdvice(string $class): array
    {
        $matchingAdvice = [];
        
        foreach ($this->advices as $advice) {
            // Simple pointcut matching - you can enhance this
            if ($this->pointcutMatches($advice['pointcut'], $class)) {
                $matchingAdvice[] = $advice;
            }
        }
        
        return $matchingAdvice;
    }

    private function pointcutMatches(string $pointcut, string $class): bool
    {
        // Handle wildcard patterns
        if (str_contains($pointcut, '*')) {
            $pattern = str_replace('*', '.*', $pointcut);
            if (str_contains($pattern, '::')) {
                $classPattern = explode('::', $pattern)[0];
                // Escape backslashes for regex
                $classPattern = str_replace('\\', '\\\\', $classPattern);
                return (bool) preg_match('/^' . $classPattern . '$/', $class);
            }
            // Escape backslashes for regex
            $pattern = str_replace('\\', '\\\\', $pattern);
            return (bool) preg_match('/^' . $pattern . '$/', $class);
        }
        
        // Handle specific class patterns
        if (str_contains($pointcut, '::')) {
            $classPattern = explode('::', $pointcut)[0];
            return $class === $classPattern;
        }
        
        return $class === $pointcut;
    }

    private function replaceWithProxy(ContainerBuilder $container, string $id, Definition $definition, string $class): void
    {
        try {
            $reflection = new ReflectionClass($class);
            
            // Determine proxy class name based on the generated proxy
            $proxyClassName = $this->getProxyClassName($class);
            
            // Try to load the proxy class if it doesn't exist
            if (!class_exists($proxyClassName)) {
                // Try to manually include the proxy class file
                $proxyFileName = $this->getProxyFileName($class);
                if (file_exists($proxyFileName)) {
                    require_once $proxyFileName;
                }
                
                // Check again if the class exists
                if (!class_exists($proxyClassName)) {
                    $this->logger->warning('Proxy class not found, skipping service replacement', [
                        'target_class' => $class,
                        'proxy_class' => $proxyClassName
                    ]);
                    return;
                }
            }
            
            // Use decoration pattern: keep original service as .inner, wrap it with proxy
            // This ensures the original service is properly instantiated
            
            // Create internal service ID for the original service
            $innerServiceId = $id . '.inner';
            
            // Clone the original definition and register it with .inner suffix
            $innerDefinition = clone $definition;
            $innerDefinition->setPublic(false); // Make inner service private
            $container->setDefinition($innerServiceId, $innerDefinition);
            
            // Create proxy definition that wraps the original
            $proxyDefinition = new Definition($proxyClassName);
            $proxyDefinition->setPublic($definition->isPublic());
            $proxyDefinition->setLazy($definition->isLazy());
            
            // Proxy constructor expects: (targetInstance, container)
            $proxyDefinition->setArguments([
                new Reference($innerServiceId),  // The original service instance
                new Reference('service_container')  // Container for aspect resolution
            ]);
            
            // Copy method calls from original to proxy (they'll be delegated)
            foreach ($definition->getMethodCalls() as $methodCall) {
                $proxyDefinition->addMethodCall($methodCall[0], $methodCall[1]);
            }
            
            // Replace the original definition with proxy
            $container->setDefinition($id, $proxyDefinition);
            
            $this->logger->info('Replaced service with proxy using decoration pattern', [
                'service_id' => $id,
                'inner_service_id' => $innerServiceId,
                'target_class' => $class,
                'proxy_class' => $proxyClassName
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to replace service with proxy', [
                'service_id' => $id,
                'target_class' => $class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function getProxyClassName(string $originalClass): string
    {
        // Use the same logic as HybridProxyGenerator to generate proxy class name
        $shortName = basename(str_replace('\\', '/', $originalClass));
        
        // Generate a hash for the class (similar to HybridProxyGenerator)
        $hash = substr(md5($originalClass), 0, 8);
        
        // Determine strategy (must match HybridProxyGenerator logic)
        $strategy = $this->determineProxyStrategy($originalClass);
        
        // Use the same namespace as HybridProxyGenerator
        return 'App\\AOPProxies\\' . $shortName . '_' . $strategy . '_' . $hash;
    }

    private function getProxyFileName(string $originalClass): string
    {
        // Use the same logic as HybridProxyGenerator to generate proxy file name
        $shortName = basename(str_replace('\\', '/', $originalClass));
        
        // Generate a hash for the class (similar to HybridProxyGenerator)
        $hash = substr(md5($originalClass), 0, 8);
        
        // Determine strategy (must match HybridProxyGenerator logic)
        $strategy = $this->determineProxyStrategy($originalClass);
        
        // Return the full path to the proxy file
        return dirname(__DIR__, 2) . '/var/proxy-classes/' . $shortName . '_' . $strategy . '_' . $hash . '.php';
    }

    private function determineProxyStrategy(string $targetClass): string
    {
        if (!class_exists($targetClass)) {
            return 'code_copying';
        }

        $reflection = new \ReflectionClass($targetClass);
        
        // Strategy 1: Final classes → Use delegation (code_copying) regardless of interfaces
        // We can't extend final classes, so we must use composition/delegation
        if ($reflection->isFinal()) {
            return 'code_copying';
        }
        
        // Strategy 2: Interface-based classes (not final) → Dynamic proxy
        if ($reflection->isInterface()) {
            return 'dynamic_proxy';
        }
        
        // Strategy 3: Non-final classes implementing interfaces → Dynamic proxy
        if (!empty($reflection->getInterfaces())) {
            return 'dynamic_proxy';
        }
        
        // Strategy 4: Non-final classes without interfaces → Extending proxy
        if (!$reflection->isAbstract() && !$reflection->isTrait()) {
            return 'extending';
        }
        
        // Strategy 5: Fallback
        return 'code_copying';
    }

    private function isClassLoadable(string $class): bool
    {
        // Skip classes that are likely to cause issues
        if (str_contains($class, 'Proxy') || 
            str_contains($class, 'Mock') ||
            str_contains($class, 'Test') ||
            str_starts_with($class, 'Symfony\\') ||
            str_starts_with($class, 'Doctrine\\') ||
            str_starts_with($class, 'Twig\\') ||
            str_starts_with($class, 'Monolog\\') ||
            str_starts_with($class, 'Psr\\') ||
            $class === 'Closure' ||
            $class === 'callable' ||
            $class === 'string' ||
            $class === 'int' ||
            $class === 'float' ||
            $class === 'bool' ||
            $class === 'array') {
            return false;
        }
        
        return true;
    }
}
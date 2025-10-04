<?php

declare(strict_types=1);

namespace App\AOP\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating appropriate logger instances for the AOP system
 */
class LoggerFactory
{
    private static ?LoggerInterface $logger = null;

    /**
     * Get the logger instance, creating one if it doesn't exist
     */
    public static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = self::createLogger();
        }

        return self::$logger;
    }

    /**
     * Set a custom logger instance
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Create the appropriate logger based on the environment
     */
    private static function createLogger(): LoggerInterface
    {
        // Check if we're in a Symfony environment with a logger service
        if (class_exists('\Symfony\Component\DependencyInjection\ContainerInterface')) {
            try {
                // Try to get the logger from Symfony container if available
                if (isset($GLOBALS['kernel']) && method_exists($GLOBALS['kernel'], 'getContainer')) {
                    $container = $GLOBALS['kernel']->getContainer();
                    if ($container && $container->has('logger')) {
                        $logger = $container->get('logger');
                        if ($logger instanceof LoggerInterface) {
                            return new AopLogger($logger, 'AOP');
                        }
                    }
                }
            } catch (\Exception $e) {
                // Fall through to console logger
            }
        }

        // Check if we're in a console environment
        if (self::isConsoleEnvironment()) {
            return new ConsoleLogger(true);
        }

        // Fallback to null logger
        return new NullLogger();
    }

    /**
     * Check if we're running in a console environment
     */
    private static function isConsoleEnvironment(): bool
    {
        return PHP_SAPI === 'cli' || 
               PHP_SAPI === 'phpdbg' || 
               defined('STDIN') || 
               (isset($_SERVER['argv']) && is_array($_SERVER['argv']));
    }

    /**
     * Create a logger for a specific component
     */
    public static function createComponentLogger(string $component): LoggerInterface
    {
        $baseLogger = self::getLogger();
        
        if ($baseLogger instanceof AopLogger) {
            return new AopLogger($baseLogger->logger, $component);
        }
        
        if ($baseLogger instanceof ConsoleLogger) {
            return new ConsoleLogger(true);
        }
        
        return $baseLogger;
    }

    /**
     * Reset the logger instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$logger = null;
    }
}

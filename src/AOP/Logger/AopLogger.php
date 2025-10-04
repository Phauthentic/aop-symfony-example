<?php

declare(strict_types=1);

namespace App\AOP\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * AOP-specific logger that provides structured logging for the AOP system
 * Implements PSR-3 LoggerInterface for compatibility
 */
class AopLogger implements LoggerInterface
{
    private LoggerInterface $logger;
    private string $context;

    public function __construct(LoggerInterface $logger, string $context = 'AOP')
    {
        $this->logger = $logger;
        $this->context = $context;
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->logger->emergency($this->formatMessage($message), $this->enrichContext($context));
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->logger->alert($this->formatMessage($message), $this->enrichContext($context));
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->logger->critical($this->formatMessage($message), $this->enrichContext($context));
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->logger->error($this->formatMessage($message), $this->enrichContext($context));
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->logger->warning($this->formatMessage($message), $this->enrichContext($context));
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->logger->notice($this->formatMessage($message), $this->enrichContext($context));
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->logger->info($this->formatMessage($message), $this->enrichContext($context));
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->logger->debug($this->formatMessage($message), $this->enrichContext($context));
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $this->formatMessage($message), $this->enrichContext($context));
    }

    /**
     * Format AOP-specific messages with emojis and structure
     */
    private function formatMessage(string|\Stringable $message): string
    {
        $message = (string) $message;
        
        // Remove emojis and clean up the message for proper logging
        $message = preg_replace('/[🔍🏗️📋📁📄✅❌⚠️🎯🎭🔗📝]/u', '', $message);
        $message = trim($message);
        
        return $message;
    }

    /**
     * Enrich context with AOP-specific information
     */
    private function enrichContext(array $context): array
    {
        return array_merge($context, [
            'component' => $this->context,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log aspect discovery
     */
    public function logAspectDiscovery(string $aspectClass): void
    {
        $this->info('Found aspect', ['aspect_class' => $aspectClass]);
    }

    /**
     * Log advice discovery
     */
    public function logAdviceDiscovery(string $aspectClass, string $method, string $adviceType, string $pointcut): void
    {
        $this->info('Found advice', [
            'aspect_class' => $aspectClass,
            'method' => $method,
            'advice_type' => $adviceType,
            'pointcut' => $pointcut,
        ]);
    }

    /**
     * Log proxy generation
     */
    public function logProxyGeneration(string $targetClass, string $proxyClass, string $strategy = null): void
    {
        $context = [
            'target_class' => $targetClass,
            'proxy_class' => $proxyClass,
        ];
        
        if ($strategy) {
            $context['strategy'] = $strategy;
        }
        
        $this->info('Generated proxy', $context);
    }

    /**
     * Log service replacement
     */
    public function logServiceReplacement(string $serviceId, string $targetClass, string $proxyClass): void
    {
        $this->info('Replaced service with proxy', [
            'service_id' => $serviceId,
            'target_class' => $targetClass,
            'proxy_class' => $proxyClass,
        ]);
    }

    /**
     * Log scanning operations
     */
    public function logScanning(string $directory, array $files): void
    {
        $this->debug('Scanning directory for aspects', [
            'directory' => $directory,
            'file_count' => count($files),
            'files' => array_map('basename', $files),
        ]);
    }

    /**
     * Log summary information
     */
    public function logSummary(array $aspects, array $advice, array $proxies): void
    {
        $this->info('AOP processing summary', [
            'aspects_count' => count($aspects),
            'advice_count' => count($advice),
            'proxies_count' => count($proxies),
        ]);
    }
}

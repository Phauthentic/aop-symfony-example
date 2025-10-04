<?php

declare(strict_types=1);

namespace App\AOP\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Console-friendly logger that implements PSR-3 LoggerInterface
 * Provides colored output and structured logging for console applications
 */
class ConsoleLogger implements LoggerInterface
{
    private array $logLevels = [
        LogLevel::EMERGENCY => ['level' => 'EMERGENCY', 'color' => "\033[41;37m"],
        LogLevel::ALERT     => ['level' => 'ALERT', 'color' => "\033[43;30m"],
        LogLevel::CRITICAL  => ['level' => 'CRITICAL', 'color' => "\033[41;37m"],
        LogLevel::ERROR     => ['level' => 'ERROR', 'color' => "\033[31m"],
        LogLevel::WARNING   => ['level' => 'WARNING', 'color' => "\033[33m"],
        LogLevel::NOTICE    => ['level' => 'NOTICE', 'color' => "\033[36m"],
        LogLevel::INFO      => ['level' => 'INFO', 'color' => "\033[32m"],
        LogLevel::DEBUG     => ['level' => 'DEBUG', 'color' => "\033[37m"],
    ];

    private string $resetColor = "\033[0m";
    private bool $useColors;

    public function __construct(bool $useColors = true)
    {
        $this->useColors = $useColors && $this->supportsColors();
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelConfig = $this->logLevels[$level] ?? $this->logLevels[LogLevel::INFO];
        $levelName = $levelConfig['level'];
        $color = $this->useColors ? $levelConfig['color'] : '';
        $reset = $this->useColors ? $this->resetColor : '';

        $formattedMessage = $this->formatMessage($message, $context);
        $timestamp = date('Y-m-d H:i:s');
        
        $output = sprintf(
            '[%s] %s%s%s: %s%s',
            $timestamp,
            $color,
            $levelName,
            $reset,
            $formattedMessage,
            PHP_EOL
        );

        // Write to stderr for error levels, stdout for others
        $stream = in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR]) 
            ? STDERR 
            : STDOUT;
        
        fwrite($stream, $output);
    }

    /**
     * Format the message with context information
     */
    private function formatMessage(string|\Stringable $message, array $context): string
    {
        $message = (string) $message;
        
        if (empty($context)) {
            return $message;
        }

        // Add context information in a readable format
        $contextStr = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $contextStr[] = sprintf('%s=[%s]', $key, implode(', ', $value));
            } else {
                $contextStr[] = sprintf('%s=%s', $key, $value);
            }
        }

        return sprintf('%s {%s}', $message, implode(', ', $contextStr));
    }

    /**
     * Check if the current environment supports colors
     */
    private function supportsColors(): bool
    {
        if (defined('STDOUT') && function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        if (function_exists('stream_isatty')) {
            return stream_isatty(STDOUT);
        }

        // Check for common environment variables
        $env = getenv();
        return isset($env['TERM']) && $env['TERM'] !== 'dumb';
    }

    /**
     * Log AOP-specific operations with emojis for console output
     */
    public function logAopOperation(string $operation, array $context = []): void
    {
        $emojiMap = [
            'scanning' => '🔍',
            'generating' => '🏗️',
            'summary' => '📋',
            'directory' => '📁',
            'files' => '📄',
            'success' => '✅',
            'error' => '❌',
            'warning' => '⚠️',
            'strategy' => '🎯',
            'dynamic' => '🎭',
            'extending' => '🔗',
            'code_modifying' => '📝',
        ];

        $emoji = $emojiMap[$operation] ?? 'ℹ️';
        $message = sprintf('%s AOP: %s', $emoji, ucfirst($operation));
        
        $this->info($message, $context);
    }

    /**
     * Log aspect discovery with console-friendly formatting
     */
    public function logAspectDiscovery(string $aspectClass): void
    {
        $this->logAopOperation('success', [
            'message' => 'Found aspect',
            'aspect_class' => $aspectClass,
        ]);
    }

    /**
     * Log advice discovery with console-friendly formatting
     */
    public function logAdviceDiscovery(string $aspectClass, string $method, string $adviceType, string $pointcut): void
    {
        $this->logAopOperation('success', [
            'message' => 'Found advice',
            'aspect_class' => $aspectClass,
            'method' => $method,
            'advice_type' => $adviceType,
            'pointcut' => $pointcut,
        ]);
    }

    /**
     * Log proxy generation with console-friendly formatting
     */
    public function logProxyGeneration(string $targetClass, string $proxyClass, string $strategy = null): void
    {
        $context = [
            'message' => 'Generated proxy',
            'target_class' => $targetClass,
            'proxy_class' => $proxyClass,
        ];
        
        if ($strategy) {
            $context['strategy'] = $strategy;
        }
        
        $this->logAopOperation('generating', $context);
    }
}

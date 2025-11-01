<?php

declare(strict_types=1);

namespace Four\Elo\Service;

/**
 * Simple file-based logger for CLI applications
 */
class Logger
{
    private const LEVEL_DEBUG = 'DEBUG';
    private const LEVEL_INFO = 'INFO';
    private const LEVEL_WARNING = 'WARNING';
    private const LEVEL_ERROR = 'ERROR';

    private ?string $logFile = null;
    private bool $echoToStdout;

    public function __construct(?string $logFile = null, bool $echoToStdout = false)
    {
        $this->logFile = $logFile;
        $this->echoToStdout = $echoToStdout;

        if ($this->logFile && !is_writable(dirname($this->logFile))) {
            throw new \RuntimeException(
                'Log directory is not writable: ' . dirname($this->logFile)
            );
        }
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log message with level
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        // Write to file if configured
        if ($this->logFile) {
            file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        }

        // Echo to stdout if enabled
        if ($this->echoToStdout) {
            echo $logLine;
        }
    }

    /**
     * Create logger with automatic log file naming
     */
    public static function createWithLogFile(string $basePath, string $name = 'export'): self
    {
        $logDir = rtrim($basePath, '/') . '/logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = sprintf(
            '%s/%s_%s.log',
            $logDir,
            $name,
            date('Y-m-d_H-i-s')
        );

        return new self($logFile, echoToStdout: false);
    }

    /**
     * Get log file path
     */
    public function getLogFile(): ?string
    {
        return $this->logFile;
    }
}

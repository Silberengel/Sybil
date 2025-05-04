<?php

namespace Sybil\Utility\Log;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use InvalidArgumentException;
use RuntimeException;

/**
 * Logger class for Sybil
 * 
 * This class provides logging functionality for the application,
 * supporting both console output and file logging with proper
 * security measures and data sanitization.
 */
class Logger implements LoggerInterface
{
    private MonologLogger $logger;
    private const DEFAULT_LOG_LEVEL = MonologLogger::DEBUG;
    private const MAX_LOG_FILES = 7;
    private const MAX_LOG_SIZE = 10485760; // 10MB
    private const SENSITIVE_KEYS = [
        'private_key', 'secret', 'password', 'token', 'key', 'nsec',
        'authorization', 'cookie', 'api_key', 'credential'
    ];

    /**
     * Constructor
     * 
     * @param string $name The logger name
     * @param string $logPath The path to the log file
     * @param int $logLevel The minimum log level
     * @throws RuntimeException If logger initialization fails
     */
    public function __construct(
        string $name = 'sybil',
        string $logPath = null,
        int $logLevel = self::DEFAULT_LOG_LEVEL
    ) {
        try {
            $this->logger = new MonologLogger($name);

            // Create formatters
            $lineFormatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                "Y-m-d H:i:s"
            );
            $jsonFormatter = new JsonFormatter();

            // Console output handler (always use line format)
            $streamHandler = new StreamHandler('php://stdout', $logLevel);
            $streamHandler->setFormatter($lineFormatter);
            $this->logger->pushHandler($streamHandler);

            // Error log handler (always use line format)
            $errorHandler = new ErrorLogHandler(
                ErrorLogHandler::OPERATING_SYSTEM,
                MonologLogger::ERROR
            );
            $errorHandler->setFormatter($lineFormatter);
            $this->logger->pushHandler($errorHandler);

            // File handler if path is provided
            if ($logPath !== null) {
                $fileHandler = new RotatingFileHandler(
                    $logPath,
                    self::MAX_LOG_FILES,
                    $logLevel,
                    true,
                    0664 // Secure file permissions
                );
                $fileHandler->setFormatter($jsonFormatter);
                $this->logger->pushHandler($fileHandler);
            }
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to initialize logger: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Sanitize sensitive data from context
     * 
     * @param array<string, mixed> $context The context to sanitize
     * @return array<string, mixed> The sanitized context
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                // Check if key contains any sensitive terms
                $isSensitive = false;
                foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                    if (stripos($key, $sensitiveKey) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive && is_string($value)) {
                    // For sensitive strings, only log length
                    $sanitized[$key] = sprintf('[REDACTED:%d]', strlen($value));
                } else {
                    $sanitized[$key] = $value;
                }
            }
        }
        return $sanitized;
    }

    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string|\Stringable $message Message to log
     * @param array<string, mixed> $context Additional context
     * @return void
     * @throws InvalidArgumentException If the log level is invalid
     */
    public function log($level, $message, array $context = []): void
    {
        if (!in_array($level, [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG
        ])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }

        $sanitizedContext = $this->sanitizeContext($context);
        $this->logger->log($level, $message, $sanitizedContext);
    }

    /**
     * System is unusable
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
} 
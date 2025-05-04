<?php

namespace Sybil\Utility\Log;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use RuntimeException;

/**
 * Factory class for creating Logger instances
 * 
 * This class provides a factory for creating Logger instances with proper
 * configuration and security measures.
 */
class LoggerFactory
{
    private static array $loggers = [];
    private const DEFAULT_LOG_LEVEL = MonologLogger::DEBUG;
    private const DEFAULT_LOG_PATH = 'var/log/sybil.log';

    /**
     * Create a new logger instance
     * 
     * @param string $name The logger name
     * @param ParameterBagInterface|null $params Optional parameter bag for configuration
     * @return LoggerInterface The logger instance
     * @throws RuntimeException If logger creation fails
     */
    public static function createLogger(
        string $name,
        ?ParameterBagInterface $params = null
    ): LoggerInterface {
        // Return existing logger if it exists
        if (isset(self::$loggers[$name])) {
            return self::$loggers[$name];
        }

        try {
            // Get configuration from parameter bag if provided
            $logLevel = $params?->get('app.log_level') ?? self::DEFAULT_LOG_LEVEL;
            $logPath = $params?->get('app.log_path') ?? self::DEFAULT_LOG_PATH;

            // Create new logger instance
            $logger = new Logger($name, $logPath, $logLevel);

            // Store logger instance
            self::$loggers[$name] = $logger;

            return $logger;
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to create logger '$name': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get an existing logger instance
     * 
     * @param string $name The logger name
     * @return LoggerInterface|null The logger instance or null if not found
     */
    public static function getLogger(string $name): ?LoggerInterface
    {
        return self::$loggers[$name] ?? null;
    }

    /**
     * Check if a logger exists
     * 
     * @param string $name The logger name
     * @return bool True if the logger exists
     */
    public static function hasLogger(string $name): bool
    {
        return isset(self::$loggers[$name]);
    }

    /**
     * Remove a logger instance
     * 
     * @param string $name The logger name
     * @return void
     */
    public static function removeLogger(string $name): void
    {
        unset(self::$loggers[$name]);
    }

    /**
     * Get all logger instances
     * 
     * @return array<string, LoggerInterface> All logger instances
     */
    public static function getAllLoggers(): array
    {
        return self::$loggers;
    }
} 
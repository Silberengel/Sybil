<?php

namespace Sybil\Exception;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Utility\Log\LoggerFactory;

/**
 * Configuration-level exception class
 */
class ConfigurationException extends SybilException
{
    // Configuration error codes
    public const ERROR_LOAD = 2000;
    public const ERROR_PARSE = 2001;
    public const ERROR_VALIDATE = 2002;
    public const ERROR_MISSING = 2003;
    public const ERROR_INVALID = 2004;
    public const ERROR_ACCESS = 2005;
    public const ERROR_WRITE = 2006;
    public const ERROR_READ = 2007;
    public const ERROR_FORMAT = 2008;
    public const ERROR_UNKNOWN = 2999;

    // Error messages
    private const ERROR_MESSAGES = [
        self::ERROR_LOAD => 'Failed to load configuration',
        self::ERROR_PARSE => 'Failed to parse configuration',
        self::ERROR_VALIDATE => 'Configuration validation failed',
        self::ERROR_MISSING => 'Required configuration is missing',
        self::ERROR_INVALID => 'Invalid configuration value',
        self::ERROR_ACCESS => 'Configuration access denied',
        self::ERROR_WRITE => 'Failed to write configuration',
        self::ERROR_READ => 'Failed to read configuration',
        self::ERROR_FORMAT => 'Invalid configuration format',
        self::ERROR_UNKNOWN => 'Unknown configuration error'
    ];

    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * Constructor
     * 
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     * @param array $context Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = self::ERROR_UNKNOWN,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        $this->logger = LoggerFactory::createLogger('configuration');
        
        $message = $message ?: (self::ERROR_MESSAGES[$code] ?? self::ERROR_MESSAGES[self::ERROR_UNKNOWN]);
        
        $this->logger->error($message, [
            'code' => $code,
            'context' => $context,
            'trace' => $this->getTraceAsString()
        ]);

        parent::__construct($message, $code, $previous, $context);
    }

    /**
     * Get the error message for a given code
     * 
     * @param int $code The error code
     * @return string The error message
     */
    public static function getMessageForCode(int $code): string
    {
        return self::ERROR_MESSAGES[$code] ?? self::ERROR_MESSAGES[self::ERROR_UNKNOWN];
    }

    /**
     * Check if the given code is a valid configuration error code
     * 
     * @param int $code The error code to check
     * @return bool True if the code is valid
     */
    public static function isValidCode(int $code): bool
    {
        return isset(self::ERROR_MESSAGES[$code]);
    }

    /**
     * Get all valid error codes
     * 
     * @return array The valid error codes
     */
    public static function getValidCodes(): array
    {
        return array_keys(self::ERROR_MESSAGES);
    }

    /**
     * Get all error messages
     * 
     * @return array The error messages
     */
    public static function getErrorMessages(): array
    {
        return self::ERROR_MESSAGES;
    }
} 
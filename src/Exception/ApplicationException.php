<?php

namespace Sybil\Exception;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Utility\Log\LoggerFactory;

/**
 * Application-level exception class
 */
class ApplicationException extends SybilException
{
    // Application error codes
    public const ERROR_INITIALIZATION = 1000;
    public const ERROR_CONFIGURATION = 1001;
    public const ERROR_SERVICE = 1002;
    public const ERROR_COMMAND = 1003;
    public const ERROR_AUTHENTICATION = 1004;
    public const ERROR_AUTHORIZATION = 1005;
    public const ERROR_VALIDATION = 1006;
    public const ERROR_PROCESSING = 1007;
    public const ERROR_STORAGE = 1008;
    public const ERROR_NETWORK = 1009;
    public const ERROR_UNKNOWN = 1999;

    // Error messages
    private const ERROR_MESSAGES = [
        self::ERROR_INITIALIZATION => 'Application initialization failed',
        self::ERROR_CONFIGURATION => 'Configuration error',
        self::ERROR_SERVICE => 'Service error',
        self::ERROR_COMMAND => 'Command execution failed',
        self::ERROR_AUTHENTICATION => 'Authentication failed',
        self::ERROR_AUTHORIZATION => 'Authorization failed',
        self::ERROR_VALIDATION => 'Validation failed',
        self::ERROR_PROCESSING => 'Processing failed',
        self::ERROR_STORAGE => 'Storage operation failed',
        self::ERROR_NETWORK => 'Network operation failed',
        self::ERROR_UNKNOWN => 'Unknown application error'
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
        $this->logger = LoggerFactory::createLogger('application');
        
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
     * Check if the given code is a valid application error code
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
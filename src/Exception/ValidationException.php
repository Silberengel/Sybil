<?php

namespace Sybil\Exception;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Utility\Log\LoggerFactory;

/**
 * Exception thrown when validation fails
 * 
 * This exception is used when:
 * - Data validation fails
 * - Schema validation fails
 * - Format validation fails
 * - Type validation fails
 * - Constraint validation fails
 */
class ValidationException extends SybilException
{
    // Validation error codes
    public const ERROR_DATA = 5000;
    public const ERROR_SCHEMA = 5001;
    public const ERROR_FORMAT = 5002;
    public const ERROR_TYPE = 5003;
    public const ERROR_CONSTRAINT = 5004;
    public const ERROR_REQUIRED = 5005;
    public const ERROR_LENGTH = 5006;
    public const ERROR_RANGE = 5007;
    public const ERROR_PATTERN = 5008;
    public const ERROR_UNKNOWN = 5999;

    // Error messages
    private const ERROR_MESSAGES = [
        self::ERROR_DATA => 'Data validation failed',
        self::ERROR_SCHEMA => 'Schema validation failed',
        self::ERROR_FORMAT => 'Format validation failed',
        self::ERROR_TYPE => 'Type validation failed',
        self::ERROR_CONSTRAINT => 'Constraint validation failed',
        self::ERROR_REQUIRED => 'Required field is missing',
        self::ERROR_LENGTH => 'Length validation failed',
        self::ERROR_RANGE => 'Range validation failed',
        self::ERROR_PATTERN => 'Pattern validation failed',
        self::ERROR_UNKNOWN => 'Unknown validation error'
    ];

    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var array<string> List of validation errors
     */
    protected array $errors = [];

    /**
     * Constructor
     * 
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     * @param array $context Additional context data
     * @param array<string> $errors List of validation errors
     */
    public function __construct(
        string $message = '',
        int $code = self::ERROR_UNKNOWN,
        ?\Throwable $previous = null,
        array $context = [],
        array $errors = []
    ) {
        $this->logger = LoggerFactory::createLogger('validation');
        
        $message = $message ?: (self::ERROR_MESSAGES[$code] ?? self::ERROR_MESSAGES[self::ERROR_UNKNOWN]);
        
        $this->logger->error($message, [
            'code' => $code,
            'context' => $context,
            'errors' => $errors,
            'trace' => $this->getTraceAsString()
        ]);

        parent::__construct($message, $code, $previous, $context);
        $this->errors = $errors;
    }

    /**
     * Get the list of validation errors
     * 
     * @return array<string> List of validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Add a validation error
     * 
     * @param string $error The validation error
     * @return self
     */
    public function addError(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    /**
     * Get a formatted error message including validation errors
     * 
     * @return string The formatted error message
     */
    public function getFormattedMessage(): string
    {
        $message = parent::getFormattedMessage();
        
        if (!empty($this->errors)) {
            $message .= " Validation errors: " . implode(', ', $this->errors);
        }
        
        return $message;
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
     * Check if the given code is a valid validation error code
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
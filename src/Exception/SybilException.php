<?php

namespace Sybil\Exception;

/**
 * Base exception class for Sybil application
 * 
 * This class provides a foundation for all Sybil exceptions with:
 * - Context data for additional error information
 * - Standard error codes
 * - Improved error message formatting
 * - Error severity levels
 */
class SybilException extends \Exception
{
    // Standard error codes
    public const ERROR_UNKNOWN = 0;
    public const ERROR_CONFIGURATION = 1000;
    public const ERROR_VALIDATION = 2000;
    public const ERROR_EVENT = 3000;
    public const ERROR_RELAY = 4000;
    public const ERROR_RECORD = 5000;

    // Error severity levels
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_DEBUG = 'debug';

    /**
     * @var array Additional context data for the exception
     */
    protected array $context = [];

    /**
     * @var string The severity level of the error
     */
    protected string $severity = self::SEVERITY_ERROR;

    /**
     * Constructor
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param \Throwable|null $previous The previous exception
     * @param array $context Additional context data
     * @param string $severity The severity level of the error
     */
    public function __construct(
        string $message = "",
        int $code = self::ERROR_UNKNOWN,
        ?\Throwable $previous = null,
        array $context = [],
        string $severity = self::SEVERITY_ERROR
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->severity = $severity;
    }

    /**
     * Get the context data
     *
     * @return array The context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set the context data
     *
     * @param array $context The context data
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add a single context value
     *
     * @param string $key The context key
     * @param mixed $value The context value
     * @return self
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get a context value
     *
     * @param string $key The context key
     * @param mixed $default The default value if key doesn't exist
     * @return mixed The context value or default
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if a context key exists
     *
     * @param string $key The context key
     * @return bool Whether the key exists
     */
    public function hasContext(string $key): bool
    {
        return isset($this->context[$key]);
    }

    /**
     * Get the severity level
     *
     * @return string The severity level
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Set the severity level
     *
     * @param string $severity The severity level
     * @return self
     */
    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * Check if the error is of a specific severity
     *
     * @param string $severity The severity level to check
     * @return bool Whether the error is of the specified severity
     */
    public function isSeverity(string $severity): bool
    {
        return $this->severity === $severity;
    }

    /**
     * Get a formatted error message including context
     *
     * @return string The formatted error message
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();
        
        if (!empty($this->context)) {
            $message .= ' Context: ' . json_encode($this->context, JSON_UNESCAPED_UNICODE);
        }
        
        return $message;
    }

    /**
     * Get a string representation of the exception
     *
     * @return string The string representation
     */
    public function __toString(): string
    {
        return sprintf(
            "[%s] %s (Code: %d, Severity: %s)",
            get_class($this),
            $this->getFormattedMessage(),
            $this->getCode(),
            $this->severity
        );
    }
} 
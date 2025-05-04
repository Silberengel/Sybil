<?php

namespace Sybil\Exception;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Utility\Log\LoggerFactory;

/**
 * Exception thrown when there is an error connecting to a Nostr relay
 * 
 * This exception is used when:
 * - Connection to relay fails
 * - Authentication with relay fails
 * - Relay is unreachable
 * - Relay connection times out
 */
class RelayConnectionException extends NostrException
{
    // Relay connection error codes
    public const ERROR_CONNECTION = 4000;
    public const ERROR_AUTHENTICATION = 4001;
    public const ERROR_TIMEOUT = 4002;
    public const ERROR_UNREACHABLE = 4003;
    public const ERROR_RATE_LIMIT = 4004;
    public const ERROR_PROTOCOL = 4005;
    public const ERROR_SSL = 4006;
    public const ERROR_DNS = 4007;
    public const ERROR_UNKNOWN = 4999;

    // Error messages
    private const ERROR_MESSAGES = [
        self::ERROR_CONNECTION => 'Failed to connect to relay',
        self::ERROR_AUTHENTICATION => 'Relay authentication failed',
        self::ERROR_TIMEOUT => 'Connection to relay timed out',
        self::ERROR_UNREACHABLE => 'Relay is unreachable',
        self::ERROR_RATE_LIMIT => 'Relay rate limit exceeded',
        self::ERROR_PROTOCOL => 'Relay protocol error',
        self::ERROR_SSL => 'SSL/TLS connection error',
        self::ERROR_DNS => 'DNS resolution error',
        self::ERROR_UNKNOWN => 'Unknown relay connection error'
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
     * @param string $relayUrl The relay URL that failed
     */
    public function __construct(
        string $message = '',
        int $code = self::ERROR_UNKNOWN,
        ?\Throwable $previous = null,
        array $context = [],
        string $relayUrl = ''
    ) {
        $this->logger = LoggerFactory::createLogger('relay_connection');
        
        $message = $message ?: (self::ERROR_MESSAGES[$code] ?? self::ERROR_MESSAGES[self::ERROR_UNKNOWN]);
        
        $this->logger->error($message, [
            'code' => $code,
            'context' => $context,
            'relay_url' => $relayUrl,
            'trace' => $this->getTraceAsString()
        ]);

        parent::__construct($message, $code, $previous, $context, '', $relayUrl);
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
     * Check if the given code is a valid relay connection error code
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
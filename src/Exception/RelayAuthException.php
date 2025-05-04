<?php

namespace Sybil\Exception;

use Psr\Log\LoggerInterface;
use Sybil\Utility\Log\LoggerFactory;

/**
 * Exception thrown when there is an error with relay authentication
 * 
 * This exception is used when:
 * - NIP-42 authentication fails
 * - NIP-98 HTTP authentication fails
 * - Authentication verification fails
 * - Authentication headers are invalid
 */
class RelayAuthException extends NostrException
{
    // General errors
    public const ERROR_AUTHENTICATION_FAILED = 4001;
    public const ERROR_VERIFICATION_FAILED = 4002;
    public const ERROR_INVALID_DATA = 4003;
    public const ERROR_UNSUPPORTED_METHOD = 4004;
    public const ERROR_TIMEOUT = 4005;
    public const ERROR_RATE_LIMIT = 4006;

    // NIP-42 specific errors
    public const ERROR_CHALLENGE_MISSING = 4101;
    public const ERROR_CHALLENGE_INVALID = 4102;
    public const ERROR_SIGNATURE_INVALID = 4103;
    public const ERROR_PUBKEY_MISMATCH = 4104;
    public const ERROR_AUTH_REJECTED = 4105;

    // NIP-98 specific errors
    public const ERROR_EVENT_CREATION_FAILED = 4201;
    public const ERROR_HEADER_CREATION_FAILED = 4202;
    public const ERROR_INVALID_EVENT_KIND = 4203;
    public const ERROR_INVALID_TIMESTAMP = 4204;
    public const ERROR_TIMESTAMP_EXPIRED = 4205;
    public const ERROR_URL_MISMATCH = 4206;
    public const ERROR_METHOD_MISMATCH = 4207;
    public const ERROR_PAYLOAD_MISMATCH = 4208;
    public const ERROR_INVALID_SIGNATURE = 4209;
    public const ERROR_MISSING_HEADER = 4210;
    public const ERROR_INVALID_HEADER_FORMAT = 4211;

    // HTTP middleware errors
    public const ERROR_MIDDLEWARE_CONFIG = 4301;
    public const ERROR_MIDDLEWARE_INIT = 4302;
    public const ERROR_MIDDLEWARE_PROCESS = 4303;
    public const ERROR_MIDDLEWARE_RESPONSE = 4304;

    // Error messages
    private const ERROR_MESSAGES = [
        // General errors
        self::ERROR_AUTHENTICATION_FAILED => 'Authentication failed',
        self::ERROR_VERIFICATION_FAILED => 'Verification failed',
        self::ERROR_INVALID_DATA => 'Invalid authentication data',
        self::ERROR_UNSUPPORTED_METHOD => 'Unsupported authentication method',
        self::ERROR_TIMEOUT => 'Authentication timeout',
        self::ERROR_RATE_LIMIT => 'Rate limit exceeded',

        // NIP-42 specific errors
        self::ERROR_CHALLENGE_MISSING => 'Authentication challenge missing',
        self::ERROR_CHALLENGE_INVALID => 'Invalid authentication challenge',
        self::ERROR_SIGNATURE_INVALID => 'Invalid signature',
        self::ERROR_PUBKEY_MISMATCH => 'Public key mismatch',
        self::ERROR_AUTH_REJECTED => 'Authentication rejected by relay',

        // NIP-98 specific errors
        self::ERROR_EVENT_CREATION_FAILED => 'Failed to create authentication event',
        self::ERROR_HEADER_CREATION_FAILED => 'Failed to create Authorization header',
        self::ERROR_INVALID_EVENT_KIND => 'Invalid event kind for HTTP authentication',
        self::ERROR_INVALID_TIMESTAMP => 'Invalid timestamp in authentication event',
        self::ERROR_TIMESTAMP_EXPIRED => 'Authentication event timestamp expired',
        self::ERROR_URL_MISMATCH => 'URL mismatch in authentication event',
        self::ERROR_METHOD_MISMATCH => 'HTTP method mismatch in authentication event',
        self::ERROR_PAYLOAD_MISMATCH => 'Request payload hash mismatch',
        self::ERROR_INVALID_SIGNATURE => 'Invalid event signature',
        self::ERROR_MISSING_HEADER => 'Missing Authorization header',
        self::ERROR_INVALID_HEADER_FORMAT => 'Invalid Authorization header format',

        // HTTP middleware errors
        self::ERROR_MIDDLEWARE_CONFIG => 'Middleware configuration error',
        self::ERROR_MIDDLEWARE_INIT => 'Middleware initialization error',
        self::ERROR_MIDDLEWARE_PROCESS => 'Middleware processing error',
        self::ERROR_MIDDLEWARE_RESPONSE => 'Middleware response error'
    ];

    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param \Throwable|null $previous The previous exception
     * @param array $context Additional context data
     * @param string $eventId The event ID
     * @param string $relayUrl The relay URL
     */
    public function __construct(
        string $message = "",
        int $code = self::ERROR_AUTHENTICATION_FAILED,
        ?\Throwable $previous = null,
        array $context = [],
        string $eventId = '',
        string $relayUrl = ''
    ) {
        $this->logger = LoggerFactory::createLogger('relay_auth');
        
        $message = $message ?: (self::ERROR_MESSAGES[$code] ?? 'Unknown relay authentication error');
        
        $this->logger->error($message, [
            'code' => $code,
            'context' => $context,
            'event_id' => $eventId,
            'relay_url' => $relayUrl,
            'trace' => $this->getTraceAsString()
        ]);

        parent::__construct($message, $code, $previous, $context, $eventId, $relayUrl);
    }

    /**
     * Get the error message for a given code
     *
     * @param int $code The error code
     * @return string The error message
     */
    public static function getMessageForCode(int $code): string
    {
        return self::ERROR_MESSAGES[$code] ?? 'Unknown relay authentication error';
    }

    /**
     * Check if the given code is a valid relay authentication error code
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
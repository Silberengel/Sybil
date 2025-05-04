<?php

namespace Sybil\Exception;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Utility\Log\LoggerFactory;

/**
 * Exception thrown when there is an error publishing a Nostr event
 * 
 * This exception is used when:
 * - Event broadcasting fails
 * - Event signing fails
 * - Event validation fails
 * - Relay communication fails
 */
class EventPublishException extends NostrException
{
    // Event publishing specific error codes
    public const ERROR_BROADCAST_FAILED = 3101;
    public const ERROR_SIGNING_FAILED = 3102;
    public const ERROR_VALIDATION_FAILED = 3103;
    public const ERROR_RELAY_COMMUNICATION = 3104;

    // Event publishing error codes
    public const ERROR_SIGNATURE = 3000;
    public const ERROR_RELAY = 3001;
    public const ERROR_NETWORK = 3002;
    public const ERROR_TIMEOUT = 3003;
    public const ERROR_RATE_LIMIT = 3004;
    public const ERROR_VALIDATION = 3005;
    public const ERROR_AUTHENTICATION = 3006;
    public const ERROR_AUTHORIZATION = 3007;
    public const ERROR_FORMAT = 3008;
    public const ERROR_UNKNOWN = 3999;

    // Error messages
    private const ERROR_MESSAGES = [
        self::ERROR_SIGNATURE => 'Event signature verification failed',
        self::ERROR_RELAY => 'Relay connection failed',
        self::ERROR_NETWORK => 'Network error occurred',
        self::ERROR_TIMEOUT => 'Operation timed out',
        self::ERROR_RATE_LIMIT => 'Rate limit exceeded',
        self::ERROR_VALIDATION => 'Event validation failed',
        self::ERROR_AUTHENTICATION => 'Authentication failed',
        self::ERROR_AUTHORIZATION => 'Authorization failed',
        self::ERROR_FORMAT => 'Invalid event format',
        self::ERROR_UNKNOWN => 'Unknown event publishing error'
    ];

    /**
     * @var array<string> List of relay URLs that failed
     */
    protected array $failedRelays = [];

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
     * @param array<string> $failedRelays List of relay URLs that failed
     */
    public function __construct(
        string $message = "",
        int $code = self::ERROR_UNKNOWN,
        ?\Throwable $previous = null,
        array $context = [],
        string $eventId = '',
        string $relayUrl = '',
        array $failedRelays = []
    ) {
        $this->logger = LoggerFactory::createLogger('event_publish');
        
        $message = $message ?: (self::ERROR_MESSAGES[$code] ?? self::ERROR_MESSAGES[self::ERROR_UNKNOWN]);
        
        $this->logger->error($message, [
            'code' => $code,
            'context' => $context,
            'trace' => $this->getTraceAsString()
        ]);

        parent::__construct($message, $code, $previous, $context, $eventId, $relayUrl);
        $this->failedRelays = $failedRelays;
    }

    /**
     * Get the list of failed relay URLs
     *
     * @return array<string> List of failed relay URLs
     */
    public function getFailedRelays(): array
    {
        return $this->failedRelays;
    }

    /**
     * Add a failed relay URL
     *
     * @param string $relayUrl The relay URL that failed
     * @return self
     */
    public function addFailedRelay(string $relayUrl): self
    {
        $this->failedRelays[] = $relayUrl;
        return $this;
    }

    /**
     * Get a formatted error message including failed relay information
     *
     * @return string The formatted error message
     */
    public function getFormattedMessage(): string
    {
        $message = parent::getFormattedMessage();
        
        if (!empty($this->failedRelays)) {
            $message .= " Failed relays: " . implode(', ', $this->failedRelays);
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
     * Check if the given code is a valid event publishing error code
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
<?php

namespace Sybil\Exception;

/**
 * Base exception class for Nostr-related errors
 * 
 * This class provides a foundation for all Nostr-related exceptions with:
 * - Event ID tracking
 * - Relay URL tracking
 * - Nostr-specific error codes
 */
class NostrException extends SybilException
{
    // Nostr-specific error codes
    public const ERROR_EVENT_INVALID = 3001;
    public const ERROR_EVENT_SIGNATURE = 3002;
    public const ERROR_EVENT_BROADCAST = 3003;
    public const ERROR_EVENT_DELETION = 3004;
    public const ERROR_EVENT_FETCH = 3005;

    /**
     * @var string The event ID associated with the error
     */
    protected string $eventId = '';

    /**
     * @var string The relay URL associated with the error
     */
    protected string $relayUrl = '';

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
        int $code = self::ERROR_EVENT_INVALID,
        ?\Throwable $previous = null,
        array $context = [],
        string $eventId = '',
        string $relayUrl = ''
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->eventId = $eventId;
        $this->relayUrl = $relayUrl;
    }

    /**
     * Get the event ID
     *
     * @return string The event ID
     */
    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * Set the event ID
     *
     * @param string $eventId The event ID
     * @return self
     */
    public function setEventId(string $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
    }

    /**
     * Get the relay URL
     *
     * @return string The relay URL
     */
    public function getRelayUrl(): string
    {
        return $this->relayUrl;
    }

    /**
     * Set the relay URL
     *
     * @param string $relayUrl The relay URL
     * @return self
     */
    public function setRelayUrl(string $relayUrl): self
    {
        $this->relayUrl = $relayUrl;
        return $this;
    }

    /**
     * Get a formatted error message including event and relay information
     *
     * @return string The formatted error message
     */
    public function getFormattedMessage(): string
    {
        $message = parent::getFormattedMessage();
        
        if ($this->eventId !== '') {
            $message .= " Event ID: {$this->eventId}";
        }
        
        if ($this->relayUrl !== '') {
            $message .= " Relay: {$this->relayUrl}";
        }
        
        return $message;
    }
} 
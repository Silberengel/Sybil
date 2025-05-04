<?php

namespace Sybil\Exception;

/**
 * Exception thrown when a record is not found
 * 
 * This exception is used when:
 * - Event is not found
 * - Relay is not found
 * - Configuration record is not found
 * - Any other record lookup fails
 */
class RecordNotFoundException extends SybilException
{
    // Record not found specific error codes
    public const ERROR_EVENT_NOT_FOUND = 5101;
    public const ERROR_RELAY_NOT_FOUND = 5102;
    public const ERROR_CONFIG_NOT_FOUND = 5103;
    public const ERROR_RECORD_NOT_FOUND = 5104;

    /**
     * @var string The record type that was not found
     */
    protected string $recordType = '';

    /**
     * @var string The record identifier that was not found
     */
    protected string $recordId = '';

    /**
     * Constructor
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param \Throwable|null $previous The previous exception
     * @param array $context Additional context data
     * @param string $recordType The record type that was not found
     * @param string $recordId The record identifier that was not found
     */
    public function __construct(
        string $message = "",
        int $code = self::ERROR_RECORD_NOT_FOUND,
        ?\Throwable $previous = null,
        array $context = [],
        string $recordType = '',
        string $recordId = ''
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->recordType = $recordType;
        $this->recordId = $recordId;
    }

    /**
     * Get the record type that was not found
     *
     * @return string The record type
     */
    public function getRecordType(): string
    {
        return $this->recordType;
    }

    /**
     * Get the record identifier that was not found
     *
     * @return string The record identifier
     */
    public function getRecordId(): string
    {
        return $this->recordId;
    }

    /**
     * Get a formatted error message including record information
     *
     * @return string The formatted error message
     */
    public function getFormattedMessage(): string
    {
        $message = parent::getFormattedMessage();
        
        if ($this->recordType !== '') {
            $message .= " Record type: {$this->recordType}";
        }
        
        if ($this->recordId !== '') {
            $message .= " Record ID: {$this->recordId}";
        }
        
        return $message;
    }
}

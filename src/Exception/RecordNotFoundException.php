<?php

namespace Sybil\Exception;

/**
 * Exception thrown when a record is not found.
 */
class RecordNotFoundException extends \Exception
{
    /**
     * Constructor.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable used for the exception chaining
     */
    public function __construct(string $message = "Record not found", int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

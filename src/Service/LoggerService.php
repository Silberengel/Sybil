<?php

namespace Sybil\Service;

use Exception;

/**
 * Service for logging
 * 
 * This service handles logging functionality, such as logging debug, info,
 * warning, and error messages.
 */
class LoggerService
{
    /**
     * @var bool Whether to enable debug logging
     */
    private bool $debugEnabled;
    
    /**
     * @var resource The stream for debug/info messages
     */
    private $debugStream;
    
    /**
     * @var resource The stream for data output
     */
    private $dataStream;
    
    /**
     * Constructor
     *
     * @param bool $debugEnabled Whether to enable debug logging
     * @param resource|null $debugStream Stream for debug/info messages (defaults to STDERR)
     * @param resource|null $dataStream Stream for data output (defaults to STDOUT)
     */
    public function __construct(
        bool $debugEnabled = false,
        $debugStream = null,
        $dataStream = null
    ) {
        $this->debugEnabled = $debugEnabled;
        $this->debugStream = $debugStream ?? STDERR;
        $this->dataStream = $dataStream ?? STDOUT;
    }
    
    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @return void
     */
    public function debug(string $message): void
    {
        if ($this->debugEnabled) {
            fwrite($this->debugStream, "Debug - " . $message . PHP_EOL);
        }
    }
    
    /**
     * Log an info message
     *
     * @param string $message The message to log
     * @return void
     */
    public function info(string $message): void
    {
        fwrite($this->debugStream, $message . PHP_EOL);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @return void
     */
    public function warning(string $message): void
    {
        fwrite($this->debugStream, "Warning: " . $message . PHP_EOL);
    }
    
    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @return void
     */
    public function error(string $message): void
    {
        fwrite($this->debugStream, "Error: " . $message . PHP_EOL);
    }
    
    /**
     * Output data that can be piped to a file or used by other scripts
     *
     * @param string $data The data to output
     * @return void
     */
    public function output(string $data): void
    {
        fwrite($this->dataStream, $data . PHP_EOL);
    }
    
    /**
     * Output JSON data that can be piped to a file or used by other scripts
     *
     * @param array|object $data The data to output as JSON
     * @param bool $pretty Whether to pretty print the JSON
     * @return void
     * @throws Exception If JSON encoding fails
     */
    public function outputJson($data, bool $pretty = false): void
    {
        $options = $pretty ? JSON_PRETTY_PRINT : 0;
        $json = json_encode($data, $options);
        if ($json === false) {
            throw new Exception("Failed to encode data as JSON: " . json_last_error_msg());
        }
        fwrite($this->dataStream, $json . PHP_EOL);
    }
    
    /**
     * Log an event to a file
     *
     * @param string $eventKind The kind of event
     * @param string $eventID The event ID
     * @param string $dTag The d-tag
     * @param string $filePath The path to the log file
     * @return bool True if successful, false otherwise
     */
    public function logEvent(string $eventKind, string $eventID, string $dTag, string $filePath): bool
    {
        try {
            $fp = fopen($filePath, "a");
            if (!$fp) {
                $this->error("Failed to open event log file: $filePath");
                return false;
            }
            
            $data = sprintf(
                "event ID: %s%s  event kind: %s%s  d Tag: %s%s",
                $eventID, PHP_EOL,
                $eventKind, PHP_EOL,
                $dTag, PHP_EOL
            );
            
            $result = fwrite($fp, $data);
            fclose($fp);
            
            return $result !== false;
        } catch (Exception $e) {
            $this->error("Error writing to event log: " . $e->getMessage());
            return false;
        }
    }
}

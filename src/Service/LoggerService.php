<?php

namespace Sybil\Service;

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
     * Constructor
     *
     * @param bool $debugEnabled Whether to enable debug logging
     */
    public function __construct(bool $debugEnabled = false)
    {
        $this->debugEnabled = $debugEnabled;
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
            echo "Debug - " . $message . PHP_EOL;
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
        echo $message . PHP_EOL;
    }
    
    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @return void
     */
    public function warning(string $message): void
    {
        echo "Warning: " . $message . PHP_EOL;
    }
    
    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @return void
     */
    public function error(string $message): void
    {
        echo "Error: " . $message . PHP_EOL;
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
        } catch (\Exception $e) {
            $this->error("Error writing to event log: " . $e->getMessage());
            return false;
        }
    }
}

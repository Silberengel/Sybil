<?php

namespace Sybil\Service;

use Exception;
use InvalidArgumentException;

/**
 * Service for logging
 * 
 * This service handles logging functionality, such as logging debug, info,
 * warning, and error messages. It supports different log levels and can output
 * to different streams.
 * 
 * @package Sybil\Service
 */
class LoggerService
{
    // Log level constants
    public const LOG_LEVEL_DEBUG = 0;
    public const LOG_LEVEL_INFO = 1;
    public const LOG_LEVEL_WARNING = 2;
    public const LOG_LEVEL_ERROR = 3;
    
    /**
     * @var int Current log level
     */
    private int $logLevel;
    
    /**
     * @var resource The stream for debug/info messages
     */
    private $debugStream;
    
    /**
     * @var resource The stream for data output
     */
    private $dataStream;
    
    /**
     * @var string|null Path to the log file for event logging
     */
    private ?string $eventLogPath = null;
    
    /**
     * @var int Maximum size of the event log file in bytes (default: 10MB)
     */
    private int $maxLogSize = 10 * 1024 * 1024;
    
    /**
     * Constructor
     *
     * @param bool $debugEnabled Whether to enable debug logging (deprecated, use setLogLevel instead)
     * @param resource|null $debugStream Stream for debug/info messages (defaults to STDERR)
     * @param resource|null $dataStream Stream for data output (defaults to STDOUT)
     */
    public function __construct(
        bool $debugEnabled = false,
        $debugStream = null,
        $dataStream = null
    ) {
        // Default to ERROR level
        $this->logLevel = self::LOG_LEVEL_ERROR;
        
        // For backward compatibility
        if ($debugEnabled) {
            $this->logLevel = self::LOG_LEVEL_DEBUG;
        }
        
        $this->debugStream = $debugStream ?? STDERR;
        $this->dataStream = $dataStream ?? STDOUT;
    }
    
    /**
     * Set the log level
     *
     * @param int $level The log level to set
     * @throws InvalidArgumentException If the log level is invalid
     */
    public function setLogLevel(int $level): void
    {
        if ($level < self::LOG_LEVEL_DEBUG || $level > self::LOG_LEVEL_ERROR) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }
        $this->logLevel = $level;
    }
    
    /**
     * Get the current log level
     *
     * @return int The current log level
     */
    public function getLogLevel(): int
    {
        return $this->logLevel;
    }
    
    /**
     * Check if a log level is enabled
     *
     * @param int $level The log level to check
     * @return bool True if the level is enabled, false otherwise
     */
    public function isLevelEnabled(int $level): bool
    {
        return $this->logLevel <= $level;
    }
    
    /**
     * Format a log message
     *
     * @param string $level The log level name
     * @param string $message The message to format
     * @return string The formatted message
     */
    private function formatLogMessage(string $level, string $message): string
    {
        $timestamp = date('Y-m-d H:i:s');
        return "[$timestamp] [$level] $message";
    }
    
    /**
     * Log a debug message
     *
     * @param string $message The message to log
     */
    public function debug(string $message): void
    {
        if ($this->isLevelEnabled(self::LOG_LEVEL_DEBUG)) {
            fwrite($this->debugStream, $this->formatLogMessage('DEBUG', $message) . PHP_EOL);
        }
    }
    
    /**
     * Log an info message
     *
     * @param string $message The message to log
     */
    public function info(string $message): void
    {
        if ($this->isLevelEnabled(self::LOG_LEVEL_INFO)) {
            fwrite($this->debugStream, $this->formatLogMessage('INFO', $message) . PHP_EOL);
        }
    }
    
    /**
     * Log a warning message
     *
     * @param string $message The message to log
     */
    public function warning(string $message): void
    {
        if ($this->isLevelEnabled(self::LOG_LEVEL_WARNING)) {
            fwrite($this->debugStream, $this->formatLogMessage('WARNING', $message) . PHP_EOL);
        }
    }
    
    /**
     * Log an error message
     *
     * @param string $message The message to log
     */
    public function error(string $message): void
    {
        if ($this->isLevelEnabled(self::LOG_LEVEL_ERROR)) {
            fwrite($this->debugStream, $this->formatLogMessage('ERROR', $message) . PHP_EOL);
        }
    }
    
    /**
     * Output data that can be piped to a file or used by other scripts
     *
     * @param string $data The data to output
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
     * Set the event log file path
     *
     * @param string $path The path to the event log file
     * @param int $maxSize Maximum size of the log file in bytes
     */
    public function setEventLogPath(string $path, int $maxSize = 10 * 1024 * 1024): void
    {
        $this->eventLogPath = $path;
        $this->maxLogSize = $maxSize;
    }
    
    /**
     * Rotate the log file if it exceeds the maximum size
     *
     * @param string $filePath The path to the log file
     * @return bool True if rotation was successful or not needed, false otherwise
     */
    private function rotateLogFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return true;
        }
        
        $size = filesize($filePath);
        if ($size === false || $size < $this->maxLogSize) {
            return true;
        }
        
        $backupPath = $filePath . '.' . date('Y-m-d-H-i-s');
        if (!rename($filePath, $backupPath)) {
            $this->error("Failed to rotate log file: $filePath");
            return false;
        }
        
        return true;
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
            // Rotate log file if needed
            if (!$this->rotateLogFile($filePath)) {
                return false;
            }
            
            $fp = fopen($filePath, "a");
            if (!$fp) {
                $this->error("Failed to open event log file: $filePath");
                return false;
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $data = sprintf(
                "[%s] event ID: %s%s  event kind: %s%s  d Tag: %s%s",
                $timestamp,
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

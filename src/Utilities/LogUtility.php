<?php
/**
 * Class LogUtility
 * 
 * This class provides utility functions for logging:
 * - Logging event data to files
 * - Handling different log levels (debug, info, warning, error)
 * - Formatting log messages
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\LogUtility;
 * 
 * // Log an event
 * LogUtility::logEventData('1', 'event-id', 'd-tag');
 * 
 * // Log messages with different levels
 * LogUtility::debug('This is a debug message');
 * LogUtility::info('This is an info message');
 * LogUtility::warning('This is a warning message');
 * LogUtility::error('This is an error message');
 * 
 * // Log a message with a custom level
 * LogUtility::log('This is a custom message', LogUtility::LOG_LEVEL_INFO);
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see ErrorHandlingUtility For error handling operations
 */

namespace Sybil\Utilities;

class LogUtility
{
    // Constants for log levels
    public const LOG_LEVEL_DEBUG = 0;
    public const LOG_LEVEL_INFO = 1;
    public const LOG_LEVEL_WARNING = 2;
    public const LOG_LEVEL_ERROR = 3;
    
    /**
     * Logs event data to a file.
     *
     * @param string $eventKind The kind of event
     * @param string $eventID The event ID
     * @param string $dTag The d-tag
     * @return bool True if successful, false otherwise
     */
    public static function logEventData(string $eventKind, string $eventID, string $dTag): bool
    {
        $fullpath = getcwd() . "/eventsCreated.yml";
        
        try {
            $fp = fopen($fullpath, "a");
            if (!$fp) {
                error_log("Failed to open event log file: $fullpath");
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
            error_log("Error writing to event log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logs a message with a specific log level.
     *
     * @param string $message The message to log
     * @param int $level The log level (use class constants)
     * @return bool True if successful, false otherwise
     */
    public static function log(string $message, int $level = self::LOG_LEVEL_INFO): bool
    {
        $levelPrefix = '';
        
        switch ($level) {
            case self::LOG_LEVEL_DEBUG:
                $levelPrefix = '[DEBUG] ';
                break;
            case self::LOG_LEVEL_INFO:
                $levelPrefix = '[INFO] ';
                break;
            case self::LOG_LEVEL_WARNING:
                $levelPrefix = '[WARNING] ';
                break;
            case self::LOG_LEVEL_ERROR:
                $levelPrefix = '[ERROR] ';
                break;
            default:
                $levelPrefix = '[INFO] ';
                break;
        }
        
        $logMessage = $levelPrefix . $message;
        
        // Log to error_log
        $result = error_log($logMessage);
        
        // Also output to console if it's not a debug message
        if ($level > self::LOG_LEVEL_DEBUG) {
            echo $logMessage . PHP_EOL;
        }
        
        return $result;
    }
    
    /**
     * Logs a debug message.
     *
     * @param string $message The message to log
     * @return bool True if successful, false otherwise
     */
    public static function debug(string $message): bool
    {
        return self::log($message, self::LOG_LEVEL_DEBUG);
    }
    
    /**
     * Logs an info message.
     *
     * @param string $message The message to log
     * @return bool True if successful, false otherwise
     */
    public static function info(string $message): bool
    {
        return self::log($message, self::LOG_LEVEL_INFO);
    }
    
    /**
     * Logs a warning message.
     *
     * @param string $message The message to log
     * @return bool True if successful, false otherwise
     */
    public static function warning(string $message): bool
    {
        return self::log($message, self::LOG_LEVEL_WARNING);
    }
    
    /**
     * Logs an error message.
     *
     * @param string $message The message to log
     * @return bool True if successful, false otherwise
     */
    public static function error(string $message): bool
    {
        return self::log($message, self::LOG_LEVEL_ERROR);
    }
}

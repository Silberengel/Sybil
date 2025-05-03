<?php
/**
 * Class ErrorHandlingUtility
 * 
 * This class provides utility functions for error handling:
 * - Executing callbacks with custom error handling
 * - Suppressing specific warnings from vendor libraries
 * - Logging errors
 * - Handling exceptions
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\ErrorHandlingUtility;
 * 
 * // Execute a callback with error handling
 * $result = ErrorHandlingUtility::executeWithErrorHandling(function() {
 *     // Code that might generate warnings
 *     return $someResult;
 * }, 'SomeFile.php');
 * 
 * // Log an error
 * ErrorHandlingUtility::logError('An error occurred');
 * 
 * // Handle an exception
 * try {
 *     // Code that might throw an exception
 * } catch (\Exception $e) {
 *     $errorMessage = ErrorHandlingUtility::handleException($e, true);
 * }
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see LogUtility For logging operations
 * @see RequestUtility For request-related operations
 */

namespace Sybil\Utilities;

use Sybil\Service\LoggerService;

class ErrorHandlingUtility
{
    /**
     * @var LoggerService The logger instance
     */
    private static ?LoggerService $logger = null;
    
    /**
     * Get the logger instance
     *
     * @return LoggerService The logger instance
     */
    private static function getLogger(): LoggerService
    {
        if (self::$logger === null) {
            self::$logger = new LoggerService();
        }
        return self::$logger;
    }
    
    /**
     * Executes a callback function with error handling for vendor library warnings.
     * 
     * @param callable $callback The function to execute
     * @param string $filePattern File pattern to match for error suppression
     * @return mixed The result of the callback function
     */
    public static function executeWithErrorHandling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
    {
        // Set up a custom error handler to catch warnings
        set_error_handler(function($errno, $errstr, $errfile, $errline) use ($filePattern) {
            // Only handle warnings from the specified file pattern
            if (($errno === E_WARNING || $errno === E_NOTICE) && 
                strpos($errfile, $filePattern) !== false) {
                // Suppress the warning
                return true; // Prevent the standard error handler from running
            }
            // For other errors, use the standard error handler
            return false;
        });
        
        try {
            // Execute the callback function
            $result = $callback();
            
            // Restore the previous error handler
            restore_error_handler();
            
            return $result;
        } catch (\Exception $e) {
            // Restore the error handler even if an exception occurs
            restore_error_handler();
            throw $e; // Re-throw the exception
        }
    }
    
    /**
     * Logs an error message to the error log.
     * 
     * @param string $message The error message to log
     * @param int $level The error level (E_ERROR, E_WARNING, etc.)
     * @return bool True if the message was logged successfully, false otherwise
     */
    public static function logError(string $message, int $level = E_ERROR): bool
    {
        $logger = self::getLogger();
        
        switch ($level) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $logger->error($message);
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $logger->warning($message);
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $logger->info($message);
                break;
            default:
                $logger->debug($message);
                break;
        }
        
        return true;
    }
    
    /**
     * Handles exceptions by logging them and optionally displaying a user-friendly message.
     * 
     * @param \Exception $exception The exception to handle
     * @param bool $displayMessage Whether to display a user-friendly message
     * @return string The error message
     */
    public static function handleException(\Exception $exception, bool $displayMessage = true): string
    {
        $message = "Error: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        
        // Log the error
        self::logError($message);
        
        // Display a user-friendly message if requested
        if ($displayMessage) {
            self::getLogger()->error("An error occurred: " . $exception->getMessage());
        }
        
        return $message;
    }
}

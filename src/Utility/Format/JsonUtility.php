<?php

namespace Sybil\Utility\Format;

use Sybil\Exception\FormatException;
use Psr\Log\LoggerInterface;
use Sybil\Utility\Log\LoggerFactory;
use JsonException;

/**
 * Utility class for working with JSON data
 * 
 * This class provides utility functions for working with JSON data,
 * including parsing and formatting.
 */
class JsonUtility
{
    private static ?LoggerInterface $logger = null;
    private const MAX_JSON_LOG_LENGTH = 1000;

    private static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createLogger('json_utility');
        }
        return self::$logger;
    }

    /**
     * Encode data to JSON string
     *
     * @param mixed $data The data to encode
     * @param bool $pretty Whether to pretty print the JSON
     * @return string The JSON string
     * @throws FormatException If encoding fails
     */
    public static function encode($data, bool $pretty = false): string
    {
        try {
            $options = JSON_THROW_ON_ERROR;
            if ($pretty) {
                $options |= JSON_PRETTY_PRINT;
            }
            
            $json = json_encode($data, $options);
            
            self::getLogger()->debug('JSON encoded successfully', [
                'length' => strlen($json),
                'pretty' => $pretty
            ]);
            
            return $json;
        } catch (JsonException $e) {
            self::getLogger()->error('Failed to encode JSON', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw new FormatException('Failed to encode JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decode JSON string
     *
     * @param string $json The JSON string to decode
     * @param bool $assoc Whether to return associative array instead of object
     * @return mixed The decoded data
     * @throws FormatException If decoding fails
     */
    public static function decode(string $json, bool $assoc = true)
    {
        try {
            $data = json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
            
            self::getLogger()->debug('JSON decoded successfully', [
                'length' => strlen($json),
                'assoc' => $assoc
            ]);
            
            return $data;
        } catch (JsonException $e) {
            self::getLogger()->error('Failed to decode JSON', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'json_preview' => self::sanitizeForLogging($json)
            ]);
            throw new FormatException('Failed to decode JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate JSON string
     *
     * @param string $json The JSON string to validate
     * @return bool Whether the JSON is valid
     */
    public static function isValid(string $json): bool
    {
        try {
            self::decode($json);
            self::getLogger()->debug('JSON validation successful', [
                'length' => strlen($json)
            ]);
            return true;
        } catch (FormatException $e) {
            self::getLogger()->debug('JSON validation failed', [
                'error' => $e->getMessage(),
                'json_preview' => self::sanitizeForLogging($json)
            ]);
            return false;
        }
    }

    /**
     * Format JSON string with proper indentation
     *
     * @param string $json The JSON string to format
     * @return string The formatted JSON string
     * @throws FormatException If formatting fails
     */
    public static function format(string $json): string
    {
        try {
            $data = self::decode($json);
            $formatted = self::encode($data, true);
            
            self::getLogger()->debug('JSON formatted successfully', [
                'original_length' => strlen($json),
                'formatted_length' => strlen($formatted)
            ]);
            
            return $formatted;
        } catch (FormatException $e) {
            self::getLogger()->error('Failed to format JSON', [
                'error' => $e->getMessage(),
                'json_preview' => self::sanitizeForLogging($json)
            ]);
            throw new FormatException('Failed to format JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get JSON error message
     *
     * @param int $errorCode The JSON error code
     * @return string The error message
     */
    public static function getErrorMessage(int $errorCode): string
    {
        $message = match($errorCode) {
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
            JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',
            JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded',
            JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given',
            default => 'Unknown error'
        };

        self::getLogger()->debug('Retrieved JSON error message', [
            'error_code' => $errorCode,
            'message' => $message
        ]);

        return $message;
    }

    /**
     * Minify JSON string
     *
     * @param string $json The JSON string to minify
     * @return string The minified JSON string
     * @throws FormatException If minification fails
     */
    public static function minify(string $json): string
    {
        try {
            $data = self::decode($json);
            $minified = self::encode($data);
            
            self::getLogger()->debug('JSON minified successfully', [
                'original_length' => strlen($json),
                'minified_length' => strlen($minified)
            ]);
            
            return $minified;
        } catch (FormatException $e) {
            self::getLogger()->error('Failed to minify JSON', [
                'error' => $e->getMessage(),
                'json_preview' => self::sanitizeForLogging($json)
            ]);
            throw new FormatException('Failed to minify JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sanitize JSON string for logging
     *
     * @param string $json The JSON string to sanitize
     * @return string The sanitized JSON string
     */
    private static function sanitizeForLogging(string $json): string
    {
        if (strlen($json) > self::MAX_JSON_LOG_LENGTH) {
            return substr($json, 0, self::MAX_JSON_LOG_LENGTH) . '...';
        }
        return $json;
    }
} 
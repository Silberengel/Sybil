<?php

namespace Sybil\Command\Traits;

use InvalidArgumentException;

/**
 * Trait for handling the key option
 * 
 * This trait provides functionality for parsing and validating the --key option
 * in commands. It allows commands to specify which environment variable contains
 * the private key to use for signing events.
 * 
 * @package Sybil\Command\Traits
 * 
 * @property LoggerService $logger The logger service instance
 * 
 * @example
 * ```php
 * class MyCommand extends BaseCommand
 * {
 *     use KeyOptionTrait;
 *     
 *     public function execute(array $args): int
 *     {
 *         $keyEnvVar = $this->parseKeyOption($args);
 *         if (!$this->validateKeyOption($keyEnvVar)) {
 *             return 1;
 *         }
 *         // Use $keyEnvVar...
 *     }
 * }
 * ```
 */
trait KeyOptionTrait
{
    /**
     * Parse the key option from command arguments
     *
     * @param array $args Command arguments
     * @return string|null The key environment variable name, or null if not specified
     */
    protected function parseKeyOption(array $args): ?string
    {
        $keyEnvVar = null;
        
        // Find the --key option
        foreach ($args as $i => $arg) {
            if ($arg === '--key' && isset($args[$i + 1])) {
                $keyEnvVar = $args[$i + 1];
                if (!$this->validateKeyOption($keyEnvVar)) {
                    $this->logger->error("Invalid key environment variable name: $keyEnvVar");
                    return null;
                }
                break;
            }
        }
        
        return $keyEnvVar;
    }
    
    /**
     * Parse arguments for both relay and key options
     *
     * @param array $args Command arguments
     * @return array Array containing [content, relayUrl, keyEnvVar]
     * @throws InvalidArgumentException If required arguments are missing
     */
    protected function parseRelayAndKeyArgs(array $args): array
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments provided');
        }
        
        $content = $args[0] ?? null;
        $relayUrl = null;
        $keyEnvVar = null;
        
        // Find the --relay and --key options
        for ($i = 1; $i < count($args); $i++) {
            if ($args[$i] === '--relay' && isset($args[$i + 1])) {
                $relayUrl = $args[$i + 1];
                if (!$this->validateRelayUrl($relayUrl)) {
                    $this->logger->error("Invalid relay URL: $relayUrl");
                    return [null, null, null];
                }
                $i++;
            } elseif ($args[$i] === '--key' && isset($args[$i + 1])) {
                $keyEnvVar = $args[$i + 1];
                if (!$this->validateKeyOption($keyEnvVar)) {
                    $this->logger->error("Invalid key environment variable name: $keyEnvVar");
                    return [null, null, null];
                }
                $i++;
            }
        }
        
        return [$content, $relayUrl, $keyEnvVar];
    }
    
    /**
     * Validate a key environment variable name
     *
     * @param string|null $keyEnvVar The key environment variable name to validate
     * @return bool True if valid, false otherwise
     */
    protected function validateKeyOption(?string $keyEnvVar): bool
    {
        if ($keyEnvVar === null) {
            return true; // Optional parameter
        }
        
        // Check if the environment variable name is valid
        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $keyEnvVar)) {
            return false;
        }
        
        // Check if the environment variable exists
        if (!isset($_ENV[$keyEnvVar])) {
            $this->logger->warning("Environment variable $keyEnvVar is not set");
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate a relay URL
     *
     * @param string $url The relay URL to validate
     * @return bool True if valid, false otherwise
     */
    protected function validateRelayUrl(string $url): bool
    {
        // Check if URL starts with ws:// or wss://
        if (!preg_match('/^wss?:\/\//', $url)) {
            return false;
        }
        
        // Check if URL is well-formed
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['host'])) {
            return false;
        }
        
        // Check if host is not empty
        if (empty($parsedUrl['host'])) {
            return false;
        }
        
        return true;
    }
} 
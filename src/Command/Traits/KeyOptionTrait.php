<?php

namespace Sybil\Command\Traits;

use InvalidArgumentException;

/**
 * Trait for handling the key option
 * 
 * This trait provides functionality for parsing and validating the --key option
 * in commands.
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
     */
    protected function parseRelayAndKeyArgs(array $args): array
    {
        $content = $args[0] ?? null;
        $relayUrl = null;
        $keyEnvVar = null;
        
        // Find the --relay and --key options
        for ($i = 1; $i < count($args); $i++) {
            if ($args[$i] === '--relay' && isset($args[$i + 1])) {
                $relayUrl = $args[$i + 1];
                $i++;
            } elseif ($args[$i] === '--key' && isset($args[$i + 1])) {
                $keyEnvVar = $args[$i + 1];
                $i++;
            }
        }
        
        return [$content, $relayUrl, $keyEnvVar];
    }
} 
<?php

namespace Sybil\Command\Traits;

use Sybil\Service\LoggerService;

/**
 * Trait RelayOptionTrait
 * 
 * This trait provides functionality for handling the --relay option in commands.
 * It can be used by any command that needs to support querying a specific relay.
 * 
 * @package Sybil\Command\Traits
 * 
 * @property LoggerService $logger The logger service instance
 * 
 * @example
 * ```php
 * class MyCommand extends BaseCommand
 * {
 *     use RelayOptionTrait;
 *     
 *     public function execute(array $args): int
 *     {
 *         [$eventId, $relayUrl] = $this->parseRelayArgs($args);
 *         if (!$this->validateEventId($eventId)) {
 *             return 1;
 *         }
 *         // Use $eventId and $relayUrl...
 *     }
 * }
 * ```
 */
trait RelayOptionTrait
{
    /**
     * Parse command arguments to extract event ID and optional relay URL
     * 
     * @param array<int,string> $args Command arguments
     * @return array{0: string|null, 1: string|null} Array containing [eventId, relayUrl]
     */
    protected function parseRelayArgs(array $args): array
    {
        $eventId = null;
        $relayUrl = null;
        
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '--relay' && isset($args[$i + 1])) {
                $relayUrl = $args[$i + 1];
                if (!$this->validateRelayUrl($relayUrl)) {
                    $this->logger->error("Invalid relay URL: $relayUrl");
                    return [null, null];
                }
                unset($args[$i], $args[$i + 1]); // Remove the --relay option and its value
                $i++; // Skip the next argument since we've used it
            }
        }
        
        // The remaining arguments are the event ID/content
        $eventId = implode(' ', array_values($args));
        
        return [$eventId, $relayUrl];
    }
    
    /**
     * Validate that an event ID is present
     * 
     * @param string|null $eventId The event ID to validate
     * @return bool True if valid, false otherwise
     */
    protected function validateEventId(?string $eventId): bool
    {
        if (empty($eventId)) {
            $this->logger->error("The event ID is missing.");
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
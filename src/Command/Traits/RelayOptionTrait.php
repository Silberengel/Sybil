<?php

namespace Sybil\Command\Traits;

use Sybil\Service\LoggerService;

/**
 * Trait RelayOptionTrait
 * 
 * This trait provides functionality for handling the --relay option in commands.
 * It can be used by any command that needs to support querying a specific relay.
 * 
 * @property LoggerService $logger The logger service instance
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
} 
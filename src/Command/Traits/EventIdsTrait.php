<?php

namespace Sybil\Command\Traits;

use InvalidArgumentException;

/**
 * Trait for handling event IDs from various input formats
 * 
 * This trait provides functionality for parsing event IDs from different input sources,
 * such as comma-separated lists or files containing event IDs.
 * 
 * @package Sybil\Command\Traits
 * 
 * @property LoggerService $logger The logger service instance
 * 
 * @example
 * ```php
 * class MyCommand extends BaseCommand
 * {
 *     use EventIdsTrait;
 *     
 *     public function execute(array $args): int
 *     {
 *         $eventIds = $this->getEventIds($args[0]);
 *         if (empty($eventIds)) {
 *             $this->logger->error("No valid event IDs found");
 *             return 1;
 *         }
 *         // Use $eventIds...
 *     }
 * }
 * ```
 */
trait EventIdsTrait
{
    /**
     * Get event IDs from input
     *
     * This method can handle both file paths and comma-separated lists of event IDs.
     * If the input is a file path, it will read the file and parse event IDs from it.
     * If the input is a string, it will treat it as a comma-separated list.
     * 
     * @param string $input Input string (comma-separated list or file path)
     * @return array Array of event IDs
     * @throws InvalidArgumentException If the input is empty or invalid
     */
    private function getEventIds(string $input): array
    {
        if (empty($input)) {
            throw new InvalidArgumentException('Input cannot be empty');
        }
        
        // Check if input is a file path
        if (file_exists($input)) {
            $content = file_get_contents($input);
            if ($content === false) {
                throw new InvalidArgumentException("Failed to read file: $input");
            }
            
            // Split by newlines and commas, then filter empty values
            $ids = array_filter(
                array_map('trim', 
                    preg_split('/[\n,]+/', $content)
                )
            );
            
            // Validate each event ID
            $validIds = [];
            foreach ($ids as $id) {
                if ($this->validateEventId($id)) {
                    $validIds[] = $id;
                } else {
                    $this->logger->warning("Invalid event ID found: $id");
                }
            }
            
            return array_values($validIds);
        }
        
        // Otherwise treat as comma-separated list
        $ids = array_map('trim', explode(',', $input));
        
        // Validate each event ID
        $validIds = [];
        foreach ($ids as $id) {
            if ($this->validateEventId($id)) {
                $validIds[] = $id;
            } else {
                $this->logger->warning("Invalid event ID found: $id");
            }
        }
        
        return array_values($validIds);
    }
    
    /**
     * Validate an event ID
     *
     * @param string $id The event ID to validate
     * @return bool True if valid, false otherwise
     */
    private function validateEventId(string $id): bool
    {
        // Check if the event ID is a valid hex string
        if (!preg_match('/^[0-9a-f]{64}$/', $id)) {
            return false;
        }
        
        return true;
    }
} 
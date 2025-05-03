<?php

namespace Sybil\Command\Traits;

/**
 * Trait for handling event IDs from various input formats
 */
trait EventIdsTrait
{
    /**
     * Get event IDs from input
     *
     * @param string $input Input string (comma-separated list or file path)
     * @return array Array of event IDs
     */
    private function getEventIds(string $input): array
    {
        // Check if input is a file path
        if (file_exists($input)) {
            $content = file_get_contents($input);
            // Split by newlines and commas, then filter empty values
            $ids = array_filter(
                array_map('trim', 
                    preg_split('/[\n,]+/', $content)
                )
            );
            return array_values($ids);
        }
        
        // Otherwise treat as comma-separated list
        return array_map('trim', explode(',', $input));
    }
} 
<?php

namespace Sybil\Event\Traits;

use Sybil\Exception\EventPublishException;

trait EventIdTrait
{
    /**
     * Generate the event ID
     * 
     * @throws EventPublishException If ID generation fails
     */
    protected function generateEventId(): void
    {
        try {
            // Get event data for ID generation
            $eventData = $this->toArray();
            unset($eventData['id']); // Remove existing ID if any
            unset($eventData['sig']); // Remove signature if any

            // Sort event data by key to ensure consistent ordering
            ksort($eventData);

            // Convert to JSON
            $json = json_encode($eventData);
            if ($json === false) {
                throw new EventPublishException('Failed to encode event data: ' . json_last_error_msg());
            }

            // Generate SHA-256 hash
            $hash = hash('sha256', $json, true);
            if ($hash === false) {
                throw new EventPublishException('Failed to generate event hash');
            }

            // Convert to hex
            $this->id = bin2hex($hash);
        } catch (\Exception $e) {
            throw new EventPublishException('Failed to generate event ID: ' . $e->getMessage());
        }
    }

    /**
     * Verify the event ID
     * 
     * @return bool True if the ID is valid
     */
    protected function verifyEventId(): bool
    {
        try {
            // Get event data for verification
            $eventData = $this->toArray();
            $id = $eventData['id'];
            unset($eventData['id']);
            unset($eventData['sig']);

            // Sort event data by key
            ksort($eventData);

            // Convert to JSON
            $json = json_encode($eventData);
            if ($json === false) {
                return false;
            }

            // Generate SHA-256 hash
            $hash = hash('sha256', $json, true);
            if ($hash === false) {
                return false;
            }

            // Compare with stored ID
            return $id === bin2hex($hash);
        } catch (\Exception $e) {
            return false;
        }
    }
} 
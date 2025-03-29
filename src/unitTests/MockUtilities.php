<?php
/**
 * Mock Utilities Class for Testing
 * 
 * This class provides mock implementations of the Utilities class methods
 * to avoid actual network connections during tests.
 */

/**
 * Mock implementation of the Utilities class
 */
class Utilities
{
    // Properties
    private string $eventID = '';
    
    // Constants
    public const DEFAULT_RELAY = 'wss://freelay.sovbit.host';

    /**
     * Constructor for Utilities
     * 
     * @param string $eventID Optional event ID to initialize with
     */
    public function __construct(string $eventID = '')
    {
        if (!empty($eventID)) {
            $this->setEventID($eventID);
        }
    }
    
    /**
     * Get the event ID
     * 
     * @return string The event ID
     */
    public function getEventID(): string
    {
        return $this->eventID;
    }
    
    /**
     * Set the event ID
     * 
     * @param string $eventID The event ID
     * @return self
     */
    public function setEventID(string $eventID): self
    {
        $this->eventID = $eventID;
        return $this;
    }

    /**
     * Mock implementation of fetch_event
     * Returns a mock event without actually connecting to relays
     * 
     * @return array The mock event data
     */
    public function fetch_event(): array
    {
        echo "Using mock fetch_event function\n";
        
        // Return a mock event with the same ID
        return [
            [
                'id' => $this->eventID,
                'kind' => 1,
                'content' => 'Mock event content',
                'created_at' => time(),
                'pubkey' => 'mock-pubkey',
                'tags' => []
            ]
        ];
    }

    /**
     * Mock implementation of broadcast_event
     * Returns a mock result without actually broadcasting to relays
     * 
     * @return array The mock result
     */
    public function broadcast_event(): array
    {
        echo "Using mock broadcast_event function\n";
        
        return [
            'success' => true,
            'message' => 'Event broadcast successfully (mocked)',
            'event_id' => $this->eventID
        ];
    }
    
    /**
     * Mock implementation of delete_event
     * Returns a mock result without actually deleting from relays
     * 
     * @return array The mock result
     */
    public function delete_event(): array
    {
        echo "Using mock delete_event function\n";
        
        return [
            'success' => true,
            'message' => 'Event deleted successfully (mocked)',
            'event_id' => 'mock-deletion-event-id-' . uniqid()
        ];
    }
}

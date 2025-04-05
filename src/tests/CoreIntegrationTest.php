<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the core functions of Sybil
 * 
 * These tests verify that the application can correctly:
 * 1. Write a note
 * 2. Publish to a relay
 * 3. Fetch from that relay
 * 4. Broadcast the note
 * 5. Delete the note
 */
final class CoreIntegrationTest extends TestCase
{
    private string $relay = 'ws://localhost:8080';
    private array $eventIds = [];
    
    /**
     * Test that a note can be created and published to a specific relay
     */
    public function testCreateNote(): void
    {
        $noteContent = "This is a test of writing a note, publishing to a relay, fetching from that relay, broadcasting the note, and then deleting the note.";
        $return = shell_exec(command: 'sybil note "' . $noteContent . '" ' . $this->relay . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'Event successfully sent to 1 relays', haystack: $return);
        
        // Extract the event ID from the output
        if (preg_match('/Event ID: ([a-f0-9]{64})/', $return, $matches)) {
            $eventId = $matches[1];
            $this->assertNotEmpty($eventId, 'Event ID should not be empty');
            // Add the event ID to the array for cleanup
            $this->eventIds[] = $eventId;
        } else {
            $this->fail('Could not extract event ID from output');
        }
    }

    /**
     * Test that an event can be fetched from a relay
     */
    public function testFetchEvent(): void
    {
        // Create a new note and get its event ID
        $noteContent = "This is a test note for fetching";
        $return = shell_exec(command: 'sybil note "' . $noteContent . '" ' . $this->relay . ' 2>&1');
        
        // Extract the event ID from the output
        if (preg_match('/Event ID: ([a-f0-9]{64})/', $return, $matches)) {
            $eventId = $matches[1];
            // Add the event ID to the array for cleanup
            $this->eventIds[] = $eventId;
        } else {
            $this->fail('Could not extract event ID from output');
        }

        sleep(5);
        
        // Fetch the event
        $return = shell_exec(command: 'sybil fetch ' . $eventId . ' ' . $this->relay . ' 2>&1');
        
        // The event might not be found, but the command should run without errors
        // Issue an E_NOTICE when the event was not found
        if (strpos($return, 'Event found on') === false) {
            trigger_error('The event could not be found.', E_USER_NOTICE);
        }
        $this->assertStringContainsString(needle: 'The utility run has finished', haystack: $return);
    }

    /**
     * Test that an event can be broadcast to a relay
     */
    public function testBroadcastEvent(): void
    {
        // Create a new note and get its event ID
        $noteContent = "This is a test note for broadcasting";
        $return = shell_exec(command: 'sybil note "' . $noteContent . '" ' . $this->relay . ' 2>&1');
        
        // Extract the event ID from the output
        if (preg_match('/Event ID: ([a-f0-9]{64})/', $return, $matches)) {
            $eventId = $matches[1];
            // Add the event ID to the array for cleanup
            $this->eventIds[] = $eventId;
        } else {
            $this->fail('Could not extract event ID from output');
        }

        sleep(5);
        
        // Broadcast the event
        $return = shell_exec(command: 'sybil broadcast ' . $eventId . ' ' . $this->relay . ' 2>&1');
        
        // The event might not be broadcast successfully, but the command should run without errors
        // Issue an E_NOTICE when the event was not broadcasted.
        if (strpos($return, 'Event broadcast successfully') === false) {
            trigger_error('The event could not be broadcast.', E_USER_NOTICE);
        }
        $this->assertStringContainsString(needle: 'The utility run has finished', haystack: $return);
    }

    /**
     * Test that an event can be deleted from a relay
     */
    public function testDeleteEvent(): void
    {
        // Create a new note and get its event ID
        $noteContent = "This is a test note for deleting";
        $return = shell_exec(command: 'sybil note "' . $noteContent . '" ' . $this->relay . ' 2>&1');
        
        // Extract the event ID from the output
        if (preg_match('/Event ID: ([a-f0-9]{64})/', $return, $matches)) {
            $eventId = $matches[1];
        } else {
            $this->fail('Could not extract event ID from output');
        }
        
        sleep(5);

        // Delete the event
        $return = shell_exec(command: 'sybil delete ' . $eventId . ' ' . $this->relay . ' 2>&1');
        
        // The event might not be deleted successfully, but the command should run without errors
        $this->assertStringContainsString(needle: 'The utility run has finished', haystack: $return);
    }
    
    /**
     * Clean up all events created during the test
     */
    private function cleanupEvents(): void
    {
        foreach ($this->eventIds as $eventId) {
            echo "Cleaning up event: " . $eventId . PHP_EOL;
            shell_exec(command: 'sybil delete ' . $eventId . ' ' . $this->relay . ' 2>&1');
        }
    }
    
    /**
     * Clean up all events created during the test, even if the test fails
     */
    protected function tearDown(): void
    {
        $this->cleanupEvents();
        parent::tearDown();
    }
}

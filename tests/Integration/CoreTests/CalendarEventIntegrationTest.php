<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Integration tests for calendar event commands
 */
class CalendarEventIntegrationTest extends CoreIntegrationTestCase
{
    private string $testUuid;
    private string $testPubkey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testUuid = '123e4567-e89b-12d3-a456-426614174000';
        $this->testPubkey = $this->testPublicKey;
    }

    /**
     * Test date-based calendar event creation
     */
    public function testDateBasedCalendarEvent(): void
    {
        $this->logger->info('Testing date-based calendar event creation');

        $command = sprintf(
            'sybil calendar:date "Test Date Event" "2024-03-20" ' .
            '--content "Test event content" ' .
            '--location "Test Location" ' .
            '--geohash "u4pruydqqvj8" ' .
            '--participant "%s" ' .
            '--tag "test" ' .
            '--reference "https://example.com/event"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Calendar event created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 31922,
            'content' => 'Test event content'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'title', 'Test Date Event');
        $this->assertTagExists($tags, 'start', '2024-03-20');
        $this->assertTagExists($tags, 'location', 'Test Location');
        $this->assertTagExists($tags, 'g', 'u4pruydqqvj8');
        $this->assertTagExists($tags, 'p', $this->testPubkey);
        $this->assertTagExists($tags, 't', 'test');
        $this->assertTagExists($tags, 'r', 'https://example.com/event');
    }

    /**
     * Test time-based calendar event creation
     */
    public function testTimeBasedCalendarEvent(): void
    {
        $this->logger->info('Testing time-based calendar event creation');

        $command = sprintf(
            'sybil calendar:time "Test Time Event" "2024-03-20T15:00:00Z" ' .
            '--content "Test event content" ' .
            '--summary "Test summary" ' .
            '--image "https://example.com/image.jpg" ' .
            '--start-tzid "UTC" ' .
            '--end-tzid "UTC" ' .
            '--location "Test Location" ' .
            '--geohash "u4pruydqqvj8" ' .
            '--participant "%s" ' .
            '--label "important" ' .
            '--tag "test" ' .
            '--reference "https://example.com/event"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Calendar event created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 31923,
            'content' => 'Test event content'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'title', 'Test Time Event');
        $this->assertTagExists($tags, 'start', '2024-03-20T15:00:00Z');
        $this->assertTagExists($tags, 'summary', 'Test summary');
        $this->assertTagExists($tags, 'image', 'https://example.com/image.jpg');
        $this->assertTagExists($tags, 'start_tzid', 'UTC');
        $this->assertTagExists($tags, 'end_tzid', 'UTC');
        $this->assertTagExists($tags, 'location', 'Test Location');
        $this->assertTagExists($tags, 'g', 'u4pruydqqvj8');
        $this->assertTagExists($tags, 'p', $this->testPubkey);
        $this->assertTagExists($tags, 'l', 'important');
        $this->assertTagExists($tags, 't', 'test');
        $this->assertTagExists($tags, 'r', 'https://example.com/event');
    }

    /**
     * Test calendar event RSVP
     */
    public function testCalendarEventRsvp(): void
    {
        $this->logger->info('Testing calendar event RSVP');

        // First create a test event
        $eventCommand = sprintf(
            'sybil calendar:time "Test Event" "2024-03-20T15:00:00Z"',
            $this->testPubkey
        );

        $eventOutput = $this->executeCommand($eventCommand);
        preg_match('/Event ID: ([a-f0-9]{64})/', $eventOutput, $matches);
        $eventId = $matches[1];

        // Now create RSVP
        $command = sprintf(
            'sybil calendar:rsvp "%s" "accepted" ' .
            '--content "I will attend" ' .
            '--event-id "%s" ' .
            '--free-busy "busy" ' .
            '--relay "wss://relay.example.com"',
            $eventId,
            $eventId
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('RSVP created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $rsvpId = $matches[1];

        // Query the event
        $event = $this->queryNote($rsvpId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 31925,
            'content' => 'I will attend'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'e', $eventId);
        $this->assertTagExists($tags, 'status', 'accepted');
        $this->assertTagExists($tags, 'free_busy', 'busy');
        $this->assertTagExists($tags, 'relay', 'wss://relay.example.com');
    }

    /**
     * Test validation of calendar event commands
     */
    public function testCalendarEventValidation(): void
    {
        $this->logger->info('Testing calendar event validation');

        // Test invalid date format
        $command = 'sybil calendar:date "Test" "invalid-date"';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid date format', $output);

        // Test invalid time format
        $command = 'sybil calendar:time "Test" "invalid-time"';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid time format', $output);

        // Test invalid RSVP status
        $command = sprintf(
            'sybil calendar:rsvp "%s" "invalid-status"',
            $this->testPubkey
        );
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid RSVP status', $output);

        // Test invalid geohash
        $command = sprintf(
            'sybil calendar:date "Test" "2024-03-20" --geohash "invalid-geohash"',
            $this->testPubkey
        );
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid geohash format', $output);
    }

    /**
     * Helper method to assert tag existence
     */
    private function assertTagExists(array $tags, string $tagName, string ...$values): void
    {
        $found = false;
        foreach ($tags as $tag) {
            if ($tag[0] === $tagName) {
                $found = true;
                for ($i = 0; $i < count($values); $i++) {
                    $this->assertEquals($values[$i], $tag[$i + 1] ?? null, "Tag $tagName should have correct value at position $i");
                }
                break;
            }
        }
        $this->assertTrue($found, "Tag $tagName should exist");
    }
} 
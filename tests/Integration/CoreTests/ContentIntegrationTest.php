<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Integration tests for content commands
 */
class ContentIntegrationTest extends CoreIntegrationTestCase
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
     * Test citation command
     */
    public function testCitation(): void
    {
        $this->logger->info('Testing citation command');

        $command = sprintf(
            'sybil citation "Test Citation" ' .
            '--content "Test citation content" ' .
            '--url "https://example.com/citation" ' .
            '--author "Test Author" ' .
            '--year "2024" ' .
            '--tag "research"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Citation created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 30017,
            'content' => 'Test citation content'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'title', 'Test Citation');
        $this->assertTagExists($tags, 'url', 'https://example.com/citation');
        $this->assertTagExists($tags, 'author', 'Test Author');
        $this->assertTagExists($tags, 'year', '2024');
        $this->assertTagExists($tags, 't', 'research');
    }

    /**
     * Test highlight command
     */
    public function testHighlight(): void
    {
        $this->logger->info('Testing highlight command');

        $command = sprintf(
            'sybil highlight "Test Highlight" ' .
            '--content "Test highlight content" ' .
            '--url "https://example.com/article" ' .
            '--context "Test context" ' .
            '--tag "important"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Highlight created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 30018,
            'content' => 'Test highlight content'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'title', 'Test Highlight');
        $this->assertTagExists($tags, 'url', 'https://example.com/article');
        $this->assertTagExists($tags, 'context', 'Test context');
        $this->assertTagExists($tags, 't', 'important');
    }

    /**
     * Test republish command
     */
    public function testRepublish(): void
    {
        $this->logger->info('Testing republish command');

        // First create a note to republish
        $noteCommand = sprintf(
            'sybil note "Original content" --tag "test"',
            $this->testPubkey
        );

        $noteOutput = $this->executeCommand($noteCommand);
        preg_match('/Event ID: ([a-f0-9]{64})/', $noteOutput, $matches);
        $originalEventId = $matches[1];

        // Now republish it
        $command = sprintf(
            'sybil republish %s ' .
            '--content "Republished content" ' .
            '--reason "Test republish"',
            $originalEventId
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Event republished successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 30019,
            'content' => 'Republished content'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'e', $originalEventId);
        $this->assertTagExists($tags, 'reason', 'Test republish');
    }

    /**
     * Test validation of content commands
     */
    public function testContentValidation(): void
    {
        $this->logger->info('Testing content command validation');

        // Test missing required arguments
        $command = 'sybil citation';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Not enough arguments', $output);

        // Test invalid URL format
        $command = 'sybil highlight "Test" --url "invalid-url"';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid URL format', $output);

        // Test invalid event ID for republish
        $command = 'sybil republish invalid-event-id';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid event ID format', $output);
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
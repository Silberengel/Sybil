<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Integration tests for ngit commands
 */
class NgitIntegrationTest extends CoreIntegrationTestCase
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
     * Test ngit:state command
     */
    public function testNgitState(): void
    {
        $this->logger->info('Testing ngit:state command');

        $command = 'sybil ngit:state';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Repository state:', $output);
    }

    /**
     * Test ngit:status command
     */
    public function testNgitStatus(): void
    {
        $this->logger->info('Testing ngit:status command');

        $command = 'sybil ngit:status';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Repository status:', $output);
    }

    /**
     * Test ngit:announce command
     */
    public function testNgitAnnounce(): void
    {
        $this->logger->info('Testing ngit:announce command');

        $command = sprintf(
            'sybil ngit:announce "Test Repository" ' .
            '--description "Test repository description" ' .
            '--url "https://example.com/repo" ' .
            '--branch "main" ' .
            '--commit "abc123" ' .
            '--tag "test"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Repository announced successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 30070,
            'content' => 'Test repository description'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'name', 'Test Repository');
        $this->assertTagExists($tags, 'url', 'https://example.com/repo');
        $this->assertTagExists($tags, 'branch', 'main');
        $this->assertTagExists($tags, 'commit', 'abc123');
        $this->assertTagExists($tags, 't', 'test');
    }

    /**
     * Test ngit:patch command
     */
    public function testNgitPatch(): void
    {
        $this->logger->info('Testing ngit:patch command');

        $command = sprintf(
            'sybil ngit:patch "Test Patch" ' .
            '--description "Test patch description" ' .
            '--base "main" ' .
            '--head "feature" ' .
            '--commit "def456" ' .
            '--tag "patch"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Patch created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 30071,
            'content' => 'Test patch description'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'title', 'Test Patch');
        $this->assertTagExists($tags, 'base', 'main');
        $this->assertTagExists($tags, 'head', 'feature');
        $this->assertTagExists($tags, 'commit', 'def456');
        $this->assertTagExists($tags, 't', 'patch');
    }

    /**
     * Test ngit:issue command
     */
    public function testNgitIssue(): void
    {
        $this->logger->info('Testing ngit:issue command');

        $command = sprintf(
            'sybil ngit:issue "Test Issue" ' .
            '--description "Test issue description" ' .
            '--priority "high" ' .
            '--status "open" ' .
            '--assignee "%s" ' .
            '--tag "bug"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Issue created successfully', $output);

        // Extract event ID from output
        preg_match('/Event ID: ([a-f0-9]{64})/', $output, $matches);
        $this->assertCount(2, $matches, 'Should find event ID in output');
        $eventId = $matches[1];

        // Query the event
        $event = $this->queryNote($eventId);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => 30072,
            'content' => 'Test issue description'
        ]);

        // Verify tags
        $this->assertArrayHasKey('tags', $event);
        $tags = $event['tags'];
        $this->assertTagExists($tags, 'd', $this->testUuid);
        $this->assertTagExists($tags, 'title', 'Test Issue');
        $this->assertTagExists($tags, 'priority', 'high');
        $this->assertTagExists($tags, 'status', 'open');
        $this->assertTagExists($tags, 'p', $this->testPubkey);
        $this->assertTagExists($tags, 't', 'bug');
    }

    /**
     * Test validation of ngit commands
     */
    public function testNgitValidation(): void
    {
        $this->logger->info('Testing ngit command validation');

        // Test missing required arguments
        $command = 'sybil ngit:announce';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Not enough arguments', $output);

        // Test invalid URL format
        $command = 'sybil ngit:announce "Test" --url "invalid-url"';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid URL format', $output);

        // Test invalid commit hash
        $command = 'sybil ngit:announce "Test" --commit "invalid-commit"';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid commit hash format', $output);

        // Test invalid priority
        $command = sprintf(
            'sybil ngit:issue "Test" --priority "invalid-priority"',
            $this->testPubkey
        );
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid priority value', $output);

        // Test invalid status
        $command = sprintf(
            'sybil ngit:issue "Test" --status "invalid-status"',
            $this->testPubkey
        );
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid status value', $output);
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
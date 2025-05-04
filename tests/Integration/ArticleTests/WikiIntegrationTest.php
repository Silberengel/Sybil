<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\ArticleTests;

use Sybil\Tests\Integration\ArticleTests\ArticleIntegrationTestCase;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\EventCreationException;
use Psr\Log\LogLevel;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Integration tests for wiki commands
 */
class WikiIntegrationTest extends ArticleIntegrationTestCase
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
     * Test wiki command
     */
    public function testWiki(): void
    {
        $this->logger->info('Testing wiki command');

        $command = sprintf(
            'sybil wiki "Test Wiki" ' .
            '--content "Test wiki content" ' .
            '--url "https://example.com/wiki" ' .
            '--author "Test Author" ' .
            '--tag "documentation"',
            $this->testPubkey
        );

        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Wiki created successfully', $output);

        // Extract event ID from output
        $eventId = $this->extractEventId($output);

        // Query the event
        $event = $this->executeCommand('sybil fetch ' . $eventId . ' ' . $this->relay);
        $this->assertNotNull($event, 'Should be able to query created event');

        // Verify event metadata
        $this->assertEventMetadata($event, [
            'kind' => '30023',
            'content' => 'Test wiki content'
        ]);

        // Verify tags
        $this->assertStringContainsString('"d": "' . $this->testUuid . '"', $event);
        $this->assertStringContainsString('"title": "Test Wiki"', $event);
        $this->assertStringContainsString('"url": "https://example.com/wiki"', $event);
        $this->assertStringContainsString('"author": "Test Author"', $event);
        $this->assertStringContainsString('"t": "documentation"', $event);
    }

    /**
     * Test wiki validation
     */
    public function testWikiValidation(): void
    {
        $this->logger->info('Testing wiki validation');

        // Test missing required arguments
        $command = 'sybil wiki';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Not enough arguments', $output);

        // Test invalid URL format
        $command = 'sybil wiki "Test" --url "invalid-url"';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid URL format', $output);
    }

    private array $wikiMetadata = [
        'title' => 'Test Wiki Article',
        'image' => 'https://example.com/wiki-test-image.jpg',
        'l' => 'en, ISO-639-1',
        'reading-direction' => 'left-to-right, top-to-bottom',
        'summary' => 'A test wiki article for integration testing'
    ];

    public function testWikiFile(): void
    {
        try {
            $this->logger->info('Testing wiki article processing');
            $testFile = $this->testDataDir . "/Wiki_testfile.md";
            $eventId = $this->processAndVerifyFile(
                'wiki',
                $testFile,
                30024,
                $this->wikiMetadata,
                ['Overview', 'History', 'Features']
            );
            $this->logger->info('Successfully tested wiki article', ['eventId' => $eventId]);
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for wiki test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (EventCreationException $e) {
            $this->logger->error('Event creation failed for wiki test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Event creation failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to process wiki file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to process wiki file: " . $e->getMessage());
        }
    }

    private function processAndVerifyFile(string $command, string $file, int $expectedEventId, array $metadata, array $sectionTitles): string
    {
        $this->logger->info('Processing and verifying wiki file', [
            'command' => $command,
            'file' => $file,
            'expectedEventId' => $expectedEventId
        ]);

        $return = $this->executeCommand($command . ' ' . $file . ' ' . $this->relay);
        
        // Verify the main wiki event
        $this->assertStringContainsString('Published ' . $expectedEventId . ' event with ID', $return);
        
        $eventId = $this->extractEventId($return, '/Published ' . $expectedEventId . ' event with ID: ([a-f0-9]{64})/');
        $this->eventIds[] = $eventId;
        
        // Verify event metadata
        $event = $this->executeCommand('sybil fetch ' . $eventId . ' ' . $this->relay);
        $this->assertStringContainsString('"kind": ' . $expectedEventId, $event);
        
        foreach ($metadata as $key => $value) {
            $this->assertStringContainsString('"' . $key . '": "' . $value . '"', $event);
        }
        
        $this->logger->info('Wiki file processed and verified successfully', ['eventId' => $eventId]);
        return $eventId;
    }
} 
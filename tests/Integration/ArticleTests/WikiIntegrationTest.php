<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\ArticleTests;

use Sybil\Tests\Integration\ArticleTests\ArticleIntegrationTestCase;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\EventCreationException;
use Psr\Log\LogLevel;

/**
 * Tests for wiki article functionality
 */
final class WikiIntegrationTest extends ArticleIntegrationTestCase
{
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
<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\ArticleTests;

use Sybil\Tests\Integration\ArticleTests\ArticleIntegrationTestCase;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\EventCreationException;
use Psr\Log\LogLevel;

/**
 * Tests for longform article functionality
 */
final class LongformIntegrationTest extends ArticleIntegrationTestCase
{
    private array $longformMetadata = [
        'title' => 'Test Longform Article',
        'image' => 'https://example.com/test-image.jpg',
        'l' => 'en, ISO-639-1',
        'reading-direction' => 'left-to-right, top-to-bottom',
        'summary' => 'A test longform article for integration testing'
    ];

    public function testLongformFile(): void
    {
        try {
            $this->logger->info('Testing longform article processing');
            $testFile = $this->testDataDir . "/Markdown_testfile.md";
            $eventId = $this->processAndVerifyFile(
                'longform',
                $testFile,
                30023,
                $this->longformMetadata,
                ['Introduction', 'Main Content', 'Conclusion']
            );
            $this->logger->info('Successfully tested longform article', ['eventId' => $eventId]);
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for longform test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (EventCreationException $e) {
            $this->logger->error('Event creation failed for longform test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Event creation failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to process longform file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to process longform file: " . $e->getMessage());
        }
    }

    private function processAndVerifyFile(string $command, string $file, int $expectedEventId, array $metadata, array $sectionTitles): string
    {
        $this->logger->info('Processing and verifying longform file', [
            'command' => $command,
            'file' => $file,
            'expectedEventId' => $expectedEventId
        ]);

        $return = $this->executeCommand($command . ' ' . $file . ' ' . $this->relay);
        
        // Verify the main longform event
        $this->assertStringContainsString('Published ' . $expectedEventId . ' event with ID', $return);
        
        $eventId = $this->extractEventId($return, '/Published ' . $expectedEventId . ' event with ID: ([a-f0-9]{64})/');
        $this->eventIds[] = $eventId;
        
        // Verify event metadata
        $event = $this->executeCommand('sybil fetch ' . $eventId . ' ' . $this->relay);
        $this->assertStringContainsString('"kind": ' . $expectedEventId, $event);
        
        foreach ($metadata as $key => $value) {
            $this->assertStringContainsString('"' . $key . '": "' . $value . '"', $event);
        }
        
        $this->logger->info('Longform file processed and verified successfully', ['eventId' => $eventId]);
        return $eventId;
    }
} 
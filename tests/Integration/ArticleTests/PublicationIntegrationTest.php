<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\ArticleTests;

use Sybil\Tests\Integration\ArticleTests\ArticleIntegrationTestCase;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\EventCreationException;
use Psr\Log\LogLevel;

/**
 * Tests for publication-related functionality
 */
final class PublicationIntegrationTest extends ArticleIntegrationTestCase
{
    private array $aesopMetadata = [
        'author' => 'Æsop',
        'version' => 'testdata',
        'image' => 'https://www.gutenberg.org/files/49010/49010-h/images/cover.jpg',
        'type' => 'book',
        'l' => 'en, ISO-639-1',
        'reading-direction' => 'left-to-right, top-to-bottom',
        'summary' => 'A short version of Aesop, that we use for testing Alexandria.',
        'i' => 'isbn:9781853261282',
        'published_on' => '0425-01-01',
        'published_by' => 'public domain',
        'p' => 'dd664d5e4016433a8cd69f005ae1480804351789b59de5af06276de65633d319',
        'source' => 'https://www.gutenberg.org/ebooks/18732'
    ];

    public function testSourcefileHas_Atags(): void
    {
        try {
            $this->logger->info('Testing publication with a-tags');
            $testFile = $this->testDataDir . "/AesopsFables_testfile_a.adoc";
            $eventId = $this->processAndVerifyFile(
                'publication',
                $testFile,
                30040,
                array_merge($this->aesopMetadata, ['t' => 'a']),
                ['Life of Aesop', 'The Wolf Turned Shepherd', 'The Stag and the Lion']
            );
            
            // Verify sections
            $this->verifySections($eventId, 'a');
            $this->logger->info('Successfully tested publication with a-tags', ['eventId' => $eventId]);
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for a-tags test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (EventCreationException $e) {
            $this->logger->error('Event creation failed for a-tags test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Event creation failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to process a-tags file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to process a-tags file: " . $e->getMessage());
        }
    }
    
    public function testSourcefileHas_Etags(): void
    {
        try {
            $this->logger->info('Testing publication with e-tags');
            $testFile = $this->testDataDir . "/AesopsFables_testfile_e.adoc";
            $eventId = $this->processAndVerifyFile(
                'publication',
                $testFile,
                30040,
                array_merge($this->aesopMetadata, ['t' => 'e']),
                ['Life of Aesop', 'The Wolf Turned Shepherd', 'The Stag and the Lion']
            );
            
            // Verify sections
            $this->verifySections($eventId, 'e');
            $this->logger->info('Successfully tested publication with e-tags', ['eventId' => $eventId]);
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for e-tags test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (EventCreationException $e) {
            $this->logger->error('Event creation failed for e-tags test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Event creation failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to process e-tags file', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to process e-tags file: " . $e->getMessage());
        }
    }

    private function verifySections(string $mainEventId, string $tagType): void
    {
        $this->logger->debug('Verifying sections', ['mainEventId' => $mainEventId, 'tagType' => $tagType]);
        $return = $this->executeCommand('sybil fetch ' . $mainEventId . ' ' . $this->relay);
        preg_match_all('/Published 30041 event with ID: ([a-f0-9]{64})/', $return, $matches);
        $sectionEventIds = $matches[1];
        
        $this->assertCount(7, $sectionEventIds);
        $this->eventIds = array_merge($this->eventIds, $sectionEventIds);
        
        foreach ($sectionEventIds as $sectionId) {
            $this->logger->debug('Verifying section', ['sectionId' => $sectionId]);
            $sectionEvent = $this->executeCommand('sybil fetch ' . $sectionId . ' ' . $this->relay);
            $this->assertStringContainsString('"kind": 30041', $sectionEvent);
            $this->assertStringContainsString('"' . $tagType . '": "' . $mainEventId . '"', $sectionEvent);
            $this->assertEventMetadata($sectionEvent, [
                'author' => 'Æsop',
                'version' => 'testdata'
            ]);
        }
    }

    private function processAndVerifyFile(string $command, string $file, int $expectedEventId, array $metadata, array $sectionTitles): string
    {
        $this->logger->info('Processing and verifying file', [
            'command' => $command,
            'file' => $file,
            'expectedEventId' => $expectedEventId
        ]);

        $return = $this->executeCommand($command . ' ' . $file . ' ' . $this->relay);
        
        // Verify the main publication event (30040)
        $this->assertStringContainsString('Published 30040 event with ID', $return);
        $this->assertStringContainsString('The publication has been written.', $return);
        
        $eventId = $this->extractEventId($return, '/Published 30040 event with ID: ([a-f0-9]{64})/');
        $this->eventIds[] = $eventId;
        
        // Verify that sections were created (30041)
        $this->assertStringContainsString('Published 30041 event with ID', $return);
        
        // Extract all section event IDs
        preg_match_all('/Published 30041 event with ID: ([a-f0-9]{64})/', $return, $matches);
        $sectionEventIds = $matches[1];
        
        // Verify that we have the expected number of sections
        // The test file has 7 sections: Life of Aesop, The Wolf Turned Shepherd, The Stag and the Lion,
        // The Fox and the Actor, The Bear and the Fox, The Wolf and the Lamb, The One-Eyed Doe
        $this->assertCount(7, $sectionEventIds);
        
        // Add section event IDs to cleanup list
        $this->eventIds = array_merge($this->eventIds, $sectionEventIds);
        
        // Verify that the main event has the correct kind and metadata
        $mainEvent = $this->executeCommand('sybil fetch ' . $eventId . ' ' . $this->relay);
        $this->assertStringContainsString('"kind": 30040', $mainEvent);
        
        // Verify YAML metadata is correctly used
        $this->assertEventMetadata($mainEvent, $metadata);
        
        // Verify that each section is properly linked to the main event
        foreach ($sectionEventIds as $sectionId) {
            $this->logger->debug('Verifying section metadata', ['sectionId' => $sectionId]);
            $sectionEvent = $this->executeCommand('sybil fetch ' . $sectionId . ' ' . $this->relay);
            $this->assertStringContainsString('"kind": 30041', $sectionEvent);
            $this->assertStringContainsString('"a": "' . $eventId . '"', $sectionEvent);
            
            // Verify that sections inherit relevant metadata
            $sectionEventData = json_decode($sectionEvent, true);
            $this->assertStringContainsString('"author": "Æsop"', $sectionEvent);
            $this->assertStringContainsString('"version": "testdata"', $sectionEvent);
        }
        
        $this->logger->info('File processed and verified successfully', ['eventId' => $eventId]);
        return $eventId;
    }

    private function assertEventMetadata(string $event, array $expectedMetadata): void
    {
        foreach ($expectedMetadata as $key => $value) {
            $this->assertStringContainsString('"' . $key . '": "' . $value . '"', $event);
        }
    }
} 
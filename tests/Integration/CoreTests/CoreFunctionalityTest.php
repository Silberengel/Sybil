<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for core functionality
 */
final class CoreFunctionalityTest extends CoreIntegrationTestCase
{
    public function testEventCreationAndRetrieval(): void
    {
        try {
            $this->logger->info('Testing event creation and retrieval');
            
            // Create a test note
            $noteContent = 'Test note ' . time();
            $note = $this->createAndPostNote($noteContent);
            $this->assertNotEmpty($note['id'], 'Note should have an ID');

            // Query for the note
            $foundNote = $this->queryNote($note['id']);
            $this->assertNotEmpty($foundNote, 'Should find the posted note');
            $this->assertEquals($noteContent, $foundNote['content'], 'Note content should match');
            $this->assertEquals($this->testPublicKey, $foundNote['pubkey'], 'Note pubkey should match');
            
            $this->logger->info('Successfully tested event creation and retrieval', [
                'note_id' => $note['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event creation and retrieval', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event creation and retrieval: ' . $e->getMessage());
        }
    }

    public function testEventDeletion(): void
    {
        try {
            $this->logger->info('Testing event deletion');
            
            // Create a test note
            $note = $this->createAndPostNote('Test note for deletion');
            $this->assertNotEmpty($note['id'], 'Note should have an ID');

            // Delete the note
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/event/' . $note['id'],
                'DELETE'
            );

            $response = $this->client->delete('/api/event/' . $note['id'], [
                'headers' => [
                    'Authorization' => $authHeader
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to delete note');

            // Verify note is deleted
            $foundNote = $this->queryNote($note['id']);
            $this->assertEmpty($foundNote, 'Note should be deleted');
            
            $this->logger->info('Successfully tested event deletion', [
                'note_id' => $note['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event deletion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event deletion: ' . $e->getMessage());
        }
    }

    public function testEventQuerying(): void
    {
        try {
            $this->logger->info('Testing event querying');
            
            // Create multiple test notes
            $notes = [];
            for ($i = 0; $i < 3; $i++) {
                $note = $this->createAndPostNote('Test note ' . $i);
                $notes[] = $note;
            }

            // Query for all notes
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'authors' => [$this->testPublicKey]
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query notes');
            
            $foundNotes = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($foundNotes, 'Response should be an array');
            $this->assertGreaterThanOrEqual(count($notes), count($foundNotes), 'Should find all created notes');
            
            $this->logger->info('Successfully tested event querying', [
                'note_count' => count($foundNotes)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event querying', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event querying: ' . $e->getMessage());
        }
    }

    public function testEventMetadata(): void
    {
        try {
            $this->logger->info('Testing event metadata');
            
            // Create a test note with metadata
            $metadata = [
                'title' => 'Test Title',
                'description' => 'Test Description',
                'tags' => ['test', 'metadata']
            ];
            
            $note = EventPreparation::createAndSignEvent(
                1,
                'Test note with metadata',
                $metadata,
                $this->testPrivateKey
            );

            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/event',
                'POST',
                json_encode($note->toArray())
            );

            $response = $this->client->post('/api/event', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'json' => $note->toArray()
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post note with metadata');
            
            $result = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($result, 'Response should be an array');
            
            // Query for the note and verify metadata
            $foundNote = $this->queryNote($result['id']);
            $this->assertNotEmpty($foundNote, 'Should find the posted note');
            
            foreach ($metadata as $key => $value) {
                $this->assertEquals($value, $foundNote[$key], "Note should have correct $key");
            }
            
            $this->logger->info('Successfully tested event metadata', [
                'note_id' => $result['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event metadata', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event metadata: ' . $e->getMessage());
        }
    }

    public function testEventKindHandling(): void
    {
        try {
            $this->logger->info('Testing event kind handling');
            
            // Test different event kinds
            $kinds = [1, 2, 3, 4, 5]; // Common event kinds
            $results = [];

            foreach ($kinds as $kind) {
                $note = EventPreparation::createAndSignEvent(
                    $kind,
                    "Test note kind $kind",
                    [],
                    $this->testPrivateKey
                );

                $authHeader = $this->httpAuth->createAuthHeader(
                    $this->getTestRelay('http') . '/api/event',
                    'POST',
                    json_encode($note->toArray())
                );

                $response = $this->client->post('/api/event', [
                    'headers' => [
                        'Authorization' => $authHeader,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $note->toArray()
                ]);

                $this->assertEquals(200, $response->getStatusCode(), "Should be able to post kind $kind note");
                
                $result = json_decode($response->getBody()->getContents(), true);
                $this->assertIsArray($result, 'Response should be an array');
                $results[] = $result;
            }

            // Verify all notes were created
            $this->assertCount(count($kinds), $results, 'Should create all test notes');
            
            $this->logger->info('Successfully tested event kind handling', [
                'kinds' => $kinds
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event kind handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event kind handling: ' . $e->getMessage());
        }
    }
} 
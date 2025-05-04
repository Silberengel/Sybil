<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for note-specific functionality
 */
final class NoteIntegrationTest extends CoreIntegrationTestCase
{
    public function testNoteCreationAndRetrieval(): void
    {
        try {
            $this->logger->info('Testing note creation and retrieval');
            
            // Create a test note
            $content = 'Test note ' . time();
            $note = $this->createAndPostNote($content);
            
            // Verify note structure
            $this->assertNotEmpty($note['id'], 'Note should have an ID');
            $this->assertEquals(1, $note['kind'], 'Note should be kind 1');
            $this->assertEquals($content, $note['content'], 'Note content should match');
            $this->assertEquals($this->testPublicKey, $note['pubkey'], 'Note pubkey should match');
            
            // Query for the note
            $foundNote = $this->queryNote($note['id']);
            $this->assertNotEmpty($foundNote, 'Should find the posted note');
            $this->assertEquals($content, $foundNote['content'], 'Note content should match');
            
            $this->logger->info('Successfully tested note creation and retrieval', [
                'note_id' => $note['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test note creation and retrieval', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test note creation and retrieval: ' . $e->getMessage());
        }
    }

    public function testNoteThreading(): void
    {
        try {
            $this->logger->info('Testing note threading');
            
            // Create root note
            $rootNote = $this->createAndPostNote('Root note');
            
            // Create reply note
            $replyContent = 'Reply to root note';
            $replyNote = EventPreparation::createAndSignEvent(
                1,
                $replyContent,
                ['tags' => [['e', $rootNote['id']]]],
                $this->testPrivateKey
            );

            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/event',
                'POST',
                json_encode($replyNote->toArray())
            );

            $response = $this->client->post('/api/event', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'json' => $replyNote->toArray()
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post reply');
            
            $result = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($result, 'Response should be an array');
            
            // Query for thread
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    '#e' => [$rootNote['id']]
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query thread');
            
            $threadNotes = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($threadNotes, 'Response should be an array');
            $this->assertNotEmpty($threadNotes, 'Should find thread notes');
            
            $this->logger->info('Successfully tested note threading', [
                'root_id' => $rootNote['id'],
                'reply_id' => $result['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test note threading', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test note threading: ' . $e->getMessage());
        }
    }

    public function testNoteMentions(): void
    {
        try {
            $this->logger->info('Testing note mentions');
            
            // Create a note with mentions
            $mentions = [
                ['p', $this->testPublicKey, 'wss://relay.example.com'],
                ['p', 'another_public_key', 'wss://another.relay.com']
            ];
            
            $note = EventPreparation::createAndSignEvent(
                1,
                'Test note with mentions',
                ['tags' => $mentions],
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

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post note with mentions');
            
            $result = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($result, 'Response should be an array');
            
            // Verify mentions
            $foundNote = $this->queryNote($result['id']);
            $this->assertNotEmpty($foundNote, 'Should find the posted note');
            $this->assertEquals($mentions, $foundNote['tags'], 'Note should have correct mentions');
            
            $this->logger->info('Successfully tested note mentions', [
                'note_id' => $result['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test note mentions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test note mentions: ' . $e->getMessage());
        }
    }

    public function testNoteSearch(): void
    {
        try {
            $this->logger->info('Testing note search');
            
            // Create notes with searchable content
            $notes = [];
            $searchTerms = ['test', 'search', 'content'];
            
            foreach ($searchTerms as $term) {
                $note = $this->createAndPostNote("Note with $term content");
                $notes[] = $note;
            }

            // Search for notes
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'search' => 'test content',
                    'authors' => [$this->testPublicKey]
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to search notes');
            
            $foundNotes = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($foundNotes, 'Response should be an array');
            $this->assertNotEmpty($foundNotes, 'Should find matching notes');
            
            $this->logger->info('Successfully tested note search', [
                'note_count' => count($foundNotes)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test note search', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test note search: ' . $e->getMessage());
        }
    }
} 
<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for feed-related functionality
 */
final class FeedIntegrationTest extends CoreIntegrationTestCase
{
    public function testUserFeed(): void
    {
        try {
            $this->logger->info('Testing user feed');
            
            // Create multiple notes
            $notes = [];
            for ($i = 0; $i < 5; $i++) {
                $note = $this->createAndPostNote("Test note $i");
                $notes[] = $note;
            }

            // Query user feed
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'authors' => [$this->testPublicKey],
                    'limit' => 10
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query user feed');
            
            $feedNotes = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($feedNotes, 'Response should be an array');
            $this->assertGreaterThanOrEqual(count($notes), count($feedNotes), 'Should find all created notes');
            
            $this->logger->info('Successfully tested user feed', [
                'note_count' => count($feedNotes)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test user feed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test user feed: ' . $e->getMessage());
        }
    }

    public function testGlobalFeed(): void
    {
        try {
            $this->logger->info('Testing global feed');
            
            // Create a test note
            $note = $this->createAndPostNote('Test note for global feed');
            
            // Query global feed
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'limit' => 10
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query global feed');
            
            $feedNotes = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($feedNotes, 'Response should be an array');
            $this->assertNotEmpty($feedNotes, 'Should find notes in global feed');
            
            $this->logger->info('Successfully tested global feed', [
                'note_count' => count($feedNotes)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test global feed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test global feed: ' . $e->getMessage());
        }
    }

    public function testFeedPagination(): void
    {
        try {
            $this->logger->info('Testing feed pagination');
            
            // Create multiple notes
            $notes = [];
            for ($i = 0; $i < 15; $i++) {
                $note = $this->createAndPostNote("Test note $i");
                $notes[] = $note;
            }

            // Query first page
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'authors' => [$this->testPublicKey],
                    'limit' => 5
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query first page');
            
            $firstPage = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($firstPage, 'Response should be an array');
            $this->assertCount(5, $firstPage, 'Should get 5 notes on first page');
            
            // Query second page
            $lastNote = end($firstPage);
            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'authors' => [$this->testPublicKey],
                    'limit' => 5,
                    'until' => $lastNote['created_at']
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query second page');
            
            $secondPage = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($secondPage, 'Response should be an array');
            $this->assertNotEmpty($secondPage, 'Should get notes on second page');
            $this->assertNotEquals($firstPage[0]['id'], $secondPage[0]['id'], 'Second page should have different notes');
            
            $this->logger->info('Successfully tested feed pagination', [
                'first_page_count' => count($firstPage),
                'second_page_count' => count($secondPage)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test feed pagination', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test feed pagination: ' . $e->getMessage());
        }
    }

    public function testFeedFilters(): void
    {
        try {
            $this->logger->info('Testing feed filters');
            
            // Create notes with different kinds
            $notes = [];
            $kinds = [1, 2, 3];
            
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
                $notes[] = $result;
            }

            // Query with kind filter
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'authors' => [$this->testPublicKey],
                    'kinds' => [1, 2]
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query with kind filter');
            
            $filteredNotes = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($filteredNotes, 'Response should be an array');
            $this->assertNotEmpty($filteredNotes, 'Should find filtered notes');
            
            foreach ($filteredNotes as $note) {
                $this->assertContains($note['kind'], [1, 2], 'Note should be kind 1 or 2');
            }
            
            $this->logger->info('Successfully tested feed filters', [
                'note_count' => count($filteredNotes)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test feed filters', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test feed filters: ' . $e->getMessage());
        }
    }
} 
<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for general event functionality
 */
final class EventIntegrationTest extends CoreIntegrationTestCase
{
    public function testEventCreationWithMetadata(): void
    {
        try {
            $this->logger->info('Testing event creation with metadata');
            
            $metadata = [
                'title' => 'Test Event',
                'description' => 'A test event with metadata',
                'tags' => [
                    ['t', 'test'],
                    ['t', 'metadata']
                ]
            ];
            
            $note = EventPreparation::createAndSignEvent(
                1,
                'Test event content',
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

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post event with metadata');
            
            $result = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($result, 'Response should be an array');
            
            // Verify metadata
            $foundEvent = $this->queryNote($result['id']);
            $this->assertNotEmpty($foundEvent, 'Should find the posted event');
            $this->assertEquals($metadata['title'], $foundEvent['title'], 'Should have correct title');
            $this->assertEquals($metadata['description'], $foundEvent['description'], 'Should have correct description');
            $this->assertEquals($metadata['tags'], $foundEvent['tags'], 'Should have correct tags');
            
            $this->logger->info('Successfully tested event creation with metadata', [
                'event_id' => $result['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event creation with metadata', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event creation with metadata: ' . $e->getMessage());
        }
    }

    public function testEventQueryingWithFilters(): void
    {
        try {
            $this->logger->info('Testing event querying with filters');
            
            // Create events with different timestamps
            $events = [];
            for ($i = 0; $i < 3; $i++) {
                $timestamp = time() - ($i * 60); // 1 minute apart
                $note = EventPreparation::createAndSignEvent(
                    1,
                    "Test event $i",
                    [],
                    $this->testPrivateKey,
                    $timestamp
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

                $this->assertEquals(200, $response->getStatusCode(), "Should be able to post event $i");
                
                $result = json_decode($response->getBody()->getContents(), true);
                $events[] = $result;
            }

            // Query with time range
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
                    'since' => time() - 180, // Last 3 minutes
                    'until' => time()
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query events with time range');
            
            $foundEvents = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($foundEvents, 'Response should be an array');
            $this->assertGreaterThanOrEqual(count($events), count($foundEvents), 'Should find all created events');
            
            $this->logger->info('Successfully tested event querying with filters', [
                'event_count' => count($foundEvents)
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event querying with filters', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event querying with filters: ' . $e->getMessage());
        }
    }

    public function testEventReactions(): void
    {
        try {
            $this->logger->info('Testing event reactions');
            
            // Create a note to react to
            $note = $this->createAndPostNote('Test note for reactions');
            
            // Create reaction event (kind 7)
            $reaction = EventPreparation::createAndSignEvent(
                7,
                '+',
                ['tags' => [['e', $note['id']]]],
                $this->testPrivateKey
            );

            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/event',
                'POST',
                json_encode($reaction->toArray())
            );

            $response = $this->client->post('/api/event', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'json' => $reaction->toArray()
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post reaction');
            
            $result = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($result, 'Response should be an array');
            
            // Query for reactions
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'kinds' => [7],
                    '#e' => [$note['id']]
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query reactions');
            
            $foundReactions = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($foundReactions, 'Response should be an array');
            $this->assertNotEmpty($foundReactions, 'Should find the reaction');
            $this->assertEquals('+', $foundReactions[0]['content'], 'Should have correct reaction content');
            
            $this->logger->info('Successfully tested event reactions', [
                'note_id' => $note['id'],
                'reaction_id' => $result['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event reactions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event reactions: ' . $e->getMessage());
        }
    }

    public function testEventReposts(): void
    {
        try {
            $this->logger->info('Testing event reposts');
            
            // Create a note to repost
            $note = $this->createAndPostNote('Test note for repost');
            
            // Create repost event (kind 6)
            $repost = EventPreparation::createAndSignEvent(
                6,
                '',
                ['tags' => [['e', $note['id']]]],
                $this->testPrivateKey
            );

            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/event',
                'POST',
                json_encode($repost->toArray())
            );

            $response = $this->client->post('/api/event', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'json' => $repost->toArray()
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post repost');
            
            $result = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($result, 'Response should be an array');
            
            // Query for reposts
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'kinds' => [6],
                    '#e' => [$note['id']]
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query reposts');
            
            $foundReposts = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($foundReposts, 'Response should be an array');
            $this->assertNotEmpty($foundReposts, 'Should find the repost');
            
            $this->logger->info('Successfully tested event reposts', [
                'note_id' => $note['id'],
                'repost_id' => $result['id']
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test event reposts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test event reposts: ' . $e->getMessage());
        }
    }
} 
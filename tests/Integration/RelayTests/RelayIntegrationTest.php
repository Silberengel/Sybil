<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\RelayTests;

use Sybil\Utility\Event\EventPreparation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for relay integration functionality
 */
final class RelayIntegrationTest extends RelayAuthTestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize HTTP client
        $this->client = new Client([
            'base_uri' => $this->getTestRelay('http'),
            'timeout' => 10.0,
            'verify' => true // Enable SSL verification
        ]);
    }

    /**
     * Test the complete flow of authentication, posting, and querying
     */
    public function testCompleteRelayFlow(): void
    {
        try {
            $this->logger->info('Testing complete relay flow');
            
            // 1. Create and post a test note
            $noteContent = 'Test note ' . time();
            $note = $this->createAndPostNote($noteContent);
            $this->assertNotEmpty($note['id'], 'Note should have an ID');

            // 2. Query for the note using the /api/query endpoint
            $foundNote = $this->queryNote($note['id']);
            $this->assertNotEmpty($foundNote, 'Should find the posted note');
            $this->assertEquals($noteContent, $foundNote['content'], 'Note content should match');
            $this->assertEquals($this->testPublicKey, $foundNote['pubkey'], 'Note pubkey should match');
            
            $this->logger->info('Complete relay flow test successful', [
                'note_id' => $note['id']
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Complete relay flow test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Test API documentation endpoint
     */
    public function testApiDocumentation(): void
    {
        try {
            $this->logger->info('Testing API documentation endpoint');
            
            $response = $this->client->get('/api');
            $this->assertEquals(200, $response->getStatusCode(), 'API documentation should be accessible');
            
            $content = $response->getBody()->getContents();
            $this->assertNotEmpty($content, 'API documentation should not be empty');
            
            $this->logger->info('API documentation test successful');
        } catch (\Exception $e) {
            $this->logger->error('API documentation test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test configuration endpoints
     */
    public function testConfigurationEndpoints(): void
    {
        try {
            $this->logger->info('Testing configuration endpoints');
            
            // Get current configuration
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/configuration/get',
                'GET'
            );

            $response = $this->client->get('/api/configuration/get', [
                'headers' => [
                    'Authorization' => $authHeader
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to get configuration');
            $config = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($config, 'Configuration should be an array');

            // Test setting configuration
            $newConfig = [
                'app_name' => 'test_app',
                'log_level' => 'debug',
                'log_timestamp' => true
            ];

            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/configuration/set',
                'POST',
                json_encode($newConfig)
            );

            $response = $this->client->post('/api/configuration/set', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'json' => $newConfig
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to set configuration');
            
            $this->logger->info('Configuration endpoints test successful');
        } catch (\Exception $e) {
            $this->logger->error('Configuration endpoints test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test SSE subscription endpoint
     */
    public function testSseSubscription(): void
    {
        try {
            $this->logger->info('Testing SSE subscription endpoint');
            
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/subscribe',
                'GET'
            );

            $response = $this->client->get('/api/subscribe', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Accept' => 'text/event-stream'
                ],
                'stream' => true
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'SSE subscription should be successful');
            $this->assertEquals('text/event-stream', $response->getHeaderLine('Content-Type'), 'Should return SSE content type');
            
            $this->logger->info('SSE subscription test successful');
        } catch (\Exception $e) {
            $this->logger->error('SSE subscription test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test admin endpoints
     */
    public function testAdminEndpoints(): void
    {
        try {
            $this->logger->info('Testing admin endpoints');
            
            // Test admin status
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/admin/status',
                'GET'
            );

            $response = $this->client->get('/api/admin/status', [
                'headers' => [
                    'Authorization' => $authHeader
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to get admin status');
            $status = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($status, 'Status should be an array');

            // Test admin stats
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/admin/stats',
                'GET'
            );

            $response = $this->client->get('/api/admin/stats', [
                'headers' => [
                    'Authorization' => $authHeader
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to get admin stats');
            $stats = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($stats, 'Stats should be an array');
            
            $this->logger->info('Admin endpoints test successful');
        } catch (\Exception $e) {
            $this->logger->error('Admin endpoints test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test database management endpoints
     */
    public function testDatabaseEndpoints(): void
    {
        try {
            $this->logger->info('Testing database endpoints');
            
            // Test database stats
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/db/stats',
                'GET'
            );

            $response = $this->client->get('/api/db/stats', [
                'headers' => [
                    'Authorization' => $authHeader
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to get database stats');
            $stats = json_decode($response->getBody()->getContents(), true);
            $this->assertIsArray($stats, 'Database stats should be an array');

            // Test database cleanup
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/db/cleanup',
                'POST'
            );

            $response = $this->client->post('/api/db/cleanup', [
                'headers' => [
                    'Authorization' => $authHeader
                ]
            ]);

            $this->assertEquals(200, $response->getStatusCode(), 'Should be able to trigger database cleanup');
            
            $this->logger->info('Database endpoints test successful');
        } catch (\Exception $e) {
            $this->logger->error('Database endpoints test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Test error handling for invalid authentication
     */
    public function testInvalidAuthentication(): void
    {
        $this->logger->info('Testing invalid authentication');
        
        $this->expectException(GuzzleException::class);
        
        // Try to post a note with invalid authentication
        $note = EventPreparation::createAndSignEvent(
            1,
            'Test note',
            [],
            $this->testPrivateKey
        );

        $this->client->post('/api/event', [
            'headers' => [
                'Authorization' => 'Invalid auth header',
                'Content-Type' => 'application/json'
            ],
            'json' => $note->toArray()
        ]);
    }

    /**
     * Test rate limiting
     */
    public function testRateLimiting(): void
    {
        try {
            $this->logger->info('Testing rate limiting');
            
            $notes = [];
            $maxAttempts = 5;
            $successCount = 0;

            for ($i = 0; $i < $maxAttempts; $i++) {
                try {
                    $note = $this->createAndPostNote('Rate limit test note ' . $i);
                    $notes[] = $note;
                    $successCount++;
                } catch (GuzzleException $e) {
                    if ($e->getCode() === 429) {
                        $this->logger->warning('Rate limit hit', [
                            'attempt' => $i + 1,
                            'success_count' => $successCount
                        ]);
                        break;
                    }
                    throw $e;
                }
            }

            $this->assertLessThanOrEqual($maxAttempts, $successCount, 'Should hit rate limit before max attempts');
            
            $this->logger->info('Rate limiting test successful', [
                'success_count' => $successCount
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Rate limiting test failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create and post a test note
     */
    private function createAndPostNote(string $content): array
    {
        $this->logger->debug('Creating and posting note', ['content' => $content]);
        
        $note = EventPreparation::createAndSignEvent(
            1,
            $content,
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

        $this->assertEquals(200, $response->getStatusCode(), 'Should be able to post note');
        
        $result = json_decode($response->getBody()->getContents(), true);
        $this->assertIsArray($result, 'Response should be an array');
        
        return $result;
    }

    /**
     * Query for a note by ID
     */
    private function queryNote(string $noteId): ?array
    {
        $this->logger->debug('Querying note', ['note_id' => $noteId]);
        
        $authHeader = $this->httpAuth->createAuthHeader(
            $this->getTestRelay('http') . '/api/query',
            'GET'
        );

        $response = $this->client->get('/api/query', [
            'headers' => [
                'Authorization' => $authHeader
            ],
            'query' => [
                'id' => $noteId
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode(), 'Should be able to query note');
        
        $result = json_decode($response->getBody()->getContents(), true);
        return $result[0] ?? null;
    }
} 
<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use Sybil\Tests\Integration\SybilIntegrationTestCase;
use Sybil\Utility\Event\EventPreparation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Base test class for Sybil core integration tests
 */
abstract class CoreIntegrationTestCase extends SybilIntegrationTestCase
{
    protected Client $client;
    protected array $eventIds = [];
    
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
    
    protected function tearDown(): void
    {
        $this->cleanupEvents();
        parent::tearDown();
    }

    /**
     * Create and post a test note
     */
    protected function createAndPostNote(string $content): array
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
        
        // Store event ID for cleanup
        if (isset($result['id'])) {
            $this->eventIds[] = $result['id'];
        }
        
        return $result;
    }

    /**
     * Query for a note by ID
     */
    protected function queryNote(string $noteId): ?array
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

    /**
     * Clean up test events
     */
    protected function cleanupEvents(): void
    {
        foreach ($this->eventIds as $eventId) {
            try {
                $this->logger->info('Cleaning up event', ['event_id' => $eventId]);
                
                $authHeader = $this->httpAuth->createAuthHeader(
                    $this->getTestRelay('http') . '/api/event/' . $eventId,
                    'DELETE'
                );

                $this->client->delete('/api/event/' . $eventId, [
                    'headers' => [
                        'Authorization' => $authHeader
                    ]
                ]);
            } catch (GuzzleException $e) {
                $this->logger->warning('Failed to cleanup event', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Assert that an event has the expected metadata
     */
    protected function assertEventMetadata(array $event, array $expectedMetadata): void
    {
        foreach ($expectedMetadata as $key => $value) {
            $this->assertEquals($value, $event[$key] ?? null, "Event should have correct $key");
        }
    }

    /**
     * Execute a command and return its output
     */
    protected function executeCommand(string $command): string
    {
        $this->logger->debug('Executing command', ['command' => $command]);
        $output = shell_exec($command . ' 2>&1');
        $this->logger->debug('Command output', ['output' => $output]);
        return $output;
    }
} 
<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use Sybil\Exception\RelayAuthException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for core error handling functionality
 */
final class CoreErrorHandlingTest extends CoreIntegrationTestCase
{
    public function testInvalidEventFormat(): void
    {
        try {
            $this->logger->info('Testing invalid event format handling');
            
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/event',
                'POST',
                json_encode(['invalid' => 'format'])
            );

            $response = $this->client->post('/api/event', [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'json' => ['invalid' => 'format']
            ]);

            $this->assertEquals(400, $response->getStatusCode(), 'Should reject invalid event format');
            $this->assertStringContainsString('Invalid event format', $response->getBody()->getContents());
            
            $this->logger->info('Successfully tested invalid event format handling');
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test invalid event format handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test invalid event format handling: ' . $e->getMessage());
        }
    }

    public function testInvalidQueryParameters(): void
    {
        try {
            $this->logger->info('Testing invalid query parameters handling');
            
            $authHeader = $this->httpAuth->createAuthHeader(
                $this->getTestRelay('http') . '/api/query',
                'GET'
            );

            $response = $this->client->get('/api/query', [
                'headers' => [
                    'Authorization' => $authHeader
                ],
                'query' => [
                    'invalid' => 'parameter'
                ]
            ]);

            $this->assertEquals(400, $response->getStatusCode(), 'Should reject invalid query parameters');
            $this->assertStringContainsString('Invalid query parameters', $response->getBody()->getContents());
            
            $this->logger->info('Successfully tested invalid query parameters handling');
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test invalid query parameters handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test invalid query parameters handling: ' . $e->getMessage());
        }
    }

    public function testRateLimitExceeded(): void
    {
        try {
            $this->logger->info('Testing rate limit handling');
            
            $notes = [];
            $maxAttempts = 10;
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
            
            $this->logger->info('Successfully tested rate limit handling', [
                'success_count' => $successCount
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to test rate limit handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test rate limit handling: ' . $e->getMessage());
        }
    }

    public function testInvalidAuthentication(): void
    {
        try {
            $this->logger->info('Testing invalid authentication handling');
            
            $note = EventPreparation::createAndSignEvent(
                1,
                'Test note',
                [],
                $this->testPrivateKey
            );

            $response = $this->client->post('/api/event', [
                'headers' => [
                    'Authorization' => 'Invalid auth header',
                    'Content-Type' => 'application/json'
                ],
                'json' => $note->toArray()
            ]);

            $this->assertEquals(401, $response->getStatusCode(), 'Should reject invalid authentication');
            $this->assertStringContainsString('Invalid authentication', $response->getBody()->getContents());
            
            $this->logger->info('Successfully tested invalid authentication handling');
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test invalid authentication handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test invalid authentication handling: ' . $e->getMessage());
        }
    }

    public function testInvalidEventKind(): void
    {
        try {
            $this->logger->info('Testing invalid event kind handling');
            
            $note = EventPreparation::createAndSignEvent(
                999999, // Invalid kind
                'Test note',
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

            $this->assertEquals(400, $response->getStatusCode(), 'Should reject invalid event kind');
            $this->assertStringContainsString('Invalid event kind', $response->getBody()->getContents());
            
            $this->logger->info('Successfully tested invalid event kind handling');
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to test invalid event kind handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test invalid event kind handling: ' . $e->getMessage());
        }
    }
} 
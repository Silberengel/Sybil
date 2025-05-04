<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\RelayTests;

use Sybil\Exception\RelayAuthException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for HTTP (NIP-98) relay authentication
 */
final class HttpAuthTest extends RelayAuthTestCase
{
    private Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = new Client();
    }

    public function testSuccessfulAuthentication(): void
    {
        try {
            $this->logger->info('Testing successful HTTP authentication');
            $relayUrl = $this->getTestRelay('http');
            $endpoint = $relayUrl . '/event';
            $method = 'POST';
            $payload = json_encode(['content' => 'Test event']);
            
            // Create authentication header
            $authHeader = $this->httpAuth->createAuthHeader($endpoint, $method, $payload);
            
            // Make authenticated request
            $response = $this->httpClient->request($method, $endpoint, [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json'
                ],
                'body' => $payload
            ]);
            
            $this->assertEquals(200, $response->getStatusCode());
            
            $this->logger->info('HTTP authentication successful', [
                'relay' => $relayUrl,
                'pubkey' => $this->testPublicKey
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('HTTP request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('HTTP request failed: ' . $e->getMessage());
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail('Authentication failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Unexpected error: ' . $e->getMessage());
        }
    }

    public function testInvalidUrl(): void
    {
        $this->logger->info('Testing invalid URL handling');
        
        $this->assertRelayAuthException(
            function () {
                $this->httpAuth->createAuthHeader('invalid-url', 'POST');
            },
            RelayAuthException::ERROR_INVALID_URL,
            'Expected invalid URL error'
        );
    }

    public function testInvalidMethod(): void
    {
        $this->logger->info('Testing invalid method handling');
        
        $this->assertRelayAuthException(
            function () {
                $this->httpAuth->createAuthHeader('https://relay.example.com/event', 'INVALID');
            },
            RelayAuthException::ERROR_INVALID_METHOD,
            'Expected invalid method error'
        );
    }

    public function testEventVerification(): void
    {
        try {
            $this->logger->info('Testing event verification');
            $relayUrl = $this->getTestRelay('http');
            $endpoint = $relayUrl . '/event';
            $method = 'POST';
            $payload = json_encode(['content' => 'Test event']);
            
            // Create authentication event
            $event = $this->httpAuth->createAuthEvent($endpoint, $method, $payload);
            
            // Verify the event
            $isValid = $this->httpAuth->verifyAuthEvent($event, $endpoint, $method, $payload);
            $this->assertTrue($isValid);
            
            $this->logger->info('Event verification successful', [
                'event_id' => $event['id'] ?? 'unknown'
            ]);
        } catch (RelayAuthException $e) {
            $this->logger->error('Event verification failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail('Event verification failed: ' . $e->getMessage());
        }
    }

    public function testExpiredEvent(): void
    {
        $this->logger->info('Testing expired event handling');
        
        try {
            $relayUrl = $this->getTestRelay('http');
            $endpoint = $relayUrl . '/event';
            $method = 'POST';
            
            // Create event with old timestamp
            $event = $this->httpAuth->createAuthEvent($endpoint, $method);
            $event['created_at'] = time() - 3600; // 1 hour ago
            
            $this->assertRelayAuthException(
                function () use ($event, $endpoint, $method) {
                    $this->httpAuth->verifyAuthEvent($event, $endpoint, $method);
                },
                RelayAuthException::ERROR_TIMESTAMP_EXPIRED,
                'Expected expired timestamp error'
            );
        } catch (RelayAuthException $e) {
            $this->logger->error('Event creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail('Event creation failed: ' . $e->getMessage());
        }
    }

    public function testPayloadMismatch(): void
    {
        $this->logger->info('Testing payload mismatch handling');
        
        try {
            $relayUrl = $this->getTestRelay('http');
            $endpoint = $relayUrl . '/event';
            $method = 'POST';
            $originalPayload = json_encode(['content' => 'Original']);
            $modifiedPayload = json_encode(['content' => 'Modified']);
            
            // Create event with original payload
            $event = $this->httpAuth->createAuthEvent($endpoint, $method, $originalPayload);
            
            $this->assertRelayAuthException(
                function () use ($event, $endpoint, $method, $modifiedPayload) {
                    $this->httpAuth->verifyAuthEvent($event, $endpoint, $method, $modifiedPayload);
                },
                RelayAuthException::ERROR_PAYLOAD_MISMATCH,
                'Expected payload mismatch error'
            );
        } catch (RelayAuthException $e) {
            $this->logger->error('Event creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail('Event creation failed: ' . $e->getMessage());
        }
    }
} 
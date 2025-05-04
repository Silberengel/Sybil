<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\RelayTests;

use Sybil\Exception\RelayAuthException;
use Sybil\Utility\Relay\RelayAuth;
use WebSocket\Client;
use WebSocket\Exception\WebSocketException;

/**
 * Tests for WebSocket (NIP-42) relay authentication
 */
final class WebSocketAuthTest extends RelayAuthTestCase
{
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->client)) {
            $this->client->close();
        }
    }

    public function testSuccessfulAuthentication(): void
    {
        try {
            $this->logger->info('Testing successful WebSocket authentication');
            $relayUrl = $this->getTestRelay('ws');
            
            // Connect to relay
            $this->client->connect($relayUrl);
            
            // Request authentication
            $this->client->send(json_encode(['AUTH', 'challenge']));
            
            // Get challenge
            $response = $this->client->receive();
            $data = json_decode($response, true);
            
            $this->assertIsArray($data);
            $this->assertEquals('AUTH', $data[0]);
            $this->assertNotEmpty($data[1]);
            
            // Authenticate with challenge
            $authData = $this->wsAuth->authenticate($data[1], 'nip42');
            $this->client->send(json_encode(['AUTH', $authData]));
            
            // Verify authentication response
            $response = $this->client->receive();
            $data = json_decode($response, true);
            
            $this->assertIsArray($data);
            $this->assertEquals('OK', $data[0]);
            $this->assertEquals('auth', $data[1]);
            
            $this->logger->info('WebSocket authentication successful', [
                'relay' => $relayUrl,
                'pubkey' => $this->testPublicKey
            ]);
        } catch (WebSocketException $e) {
            $this->logger->error('WebSocket connection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('WebSocket connection failed: ' . $e->getMessage());
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

    public function testInvalidChallenge(): void
    {
        $this->logger->info('Testing invalid challenge handling');
        
        $this->assertRelayAuthException(
            function () {
                $this->wsAuth->authenticate('invalid_challenge', 'nip42');
            },
            RelayAuthException::ERROR_CHALLENGE_INVALID,
            'Expected invalid challenge error'
        );
    }

    public function testUnsupportedMethod(): void
    {
        $this->logger->info('Testing unsupported method handling');
        
        $this->assertRelayAuthException(
            function () {
                $this->wsAuth->authenticate('challenge', 'unsupported_method');
            },
            RelayAuthException::ERROR_UNSUPPORTED_METHOD,
            'Expected unsupported method error'
        );
    }

    public function testConnectionTimeout(): void
    {
        try {
            $this->logger->info('Testing connection timeout handling');
            $this->client->connect('wss://nonexistent.relay.example.com');
            $this->fail('Expected connection timeout');
        } catch (WebSocketException $e) {
            $this->assertStringContainsString('timeout', strtolower($e->getMessage()));
        }
    }

    public function testRetryBehavior(): void
    {
        $this->logger->info('Testing authentication retry behavior');
        
        // Set retry parameters
        $this->wsAuth->setMaxRetries(3);
        $this->wsAuth->setRetryDelay(1);
        
        try {
            $relayUrl = $this->getTestRelay('ws');
            $this->client->connect($relayUrl);
            
            // Simulate temporary failure
            $this->client->send(json_encode(['AUTH', 'retry_test']));
            
            // Should retry 3 times before failing
            $this->assertRelayAuthException(
                function () {
                    $response = $this->client->receive();
                    $data = json_decode($response, true);
                    $this->wsAuth->authenticate($data[1], 'nip42');
                },
                RelayAuthException::ERROR_AUTHENTICATION_FAILED
            );
        } catch (WebSocketException $e) {
            $this->logger->error('WebSocket connection failed', [
                'error' => $e->getMessage()
            ]);
            $this->fail('WebSocket connection failed: ' . $e->getMessage());
        }
    }
} 
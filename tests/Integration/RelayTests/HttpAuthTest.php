<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\RelayTests;

use GuzzleHttp\Exception\GuzzleException;
use Sybil\Exception\RelayAuthException;

/**
 * Integration tests for HTTP relay authentication
 */
class HttpAuthTest extends RelayAuthTestCase
{
    private string $testUuid;
    private string $testPubkey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testUuid = '123e4567-e89b-12d3-a456-426614174000';
        $this->testPubkey = $this->testPublicKey;
    }

    /**
     * Test HTTP authentication header creation
     */
    public function testHttpAuthHeader(): void
    {
        $this->logger->info('Testing HTTP authentication header creation');

        $url = $this->getTestRelay('http') . '/api/event';
        $method = 'POST';
        $body = json_encode(['test' => 'data']);

        $authHeader = $this->httpAuth->createAuthHeader($url, $method, $body);
        $this->assertNotEmpty($authHeader, 'Auth header should not be empty');
        $this->assertStringStartsWith('Nostr ', $authHeader, 'Auth header should start with Nostr');
    }

    /**
     * Test HTTP authentication with valid credentials
     */
    public function testHttpAuthValid(): void
    {
        $this->logger->info('Testing HTTP authentication with valid credentials');

        $url = $this->getTestRelay('http') . '/api/event';
        $method = 'POST';
        $body = json_encode(['test' => 'data']);

        $authHeader = $this->httpAuth->createAuthHeader($url, $method, $body);

        $this->assertRelayAuthException(
            function () use ($authHeader) {
                $this->httpAuth->verifyAuthHeader($authHeader);
            },
            0,
            'Valid auth header should not throw exception'
        );
    }

    /**
     * Test HTTP authentication with invalid credentials
     */
    public function testHttpAuthInvalid(): void
    {
        $this->logger->info('Testing HTTP authentication with invalid credentials');

        $this->assertRelayAuthException(
            function () {
                $this->httpAuth->verifyAuthHeader('Invalid auth header');
            },
            RelayAuthException::INVALID_AUTH_HEADER,
            'Invalid auth header should throw exception'
        );
    }

    /**
     * Test HTTP authentication with expired credentials
     */
    public function testHttpAuthExpired(): void
    {
        $this->logger->info('Testing HTTP authentication with expired credentials');

        $url = $this->getTestRelay('http') . '/api/event';
        $method = 'POST';
        $body = json_encode(['test' => 'data']);

        // Create auth header with expired timestamp
        $authHeader = $this->httpAuth->createAuthHeader($url, $method, $body, time() - 3600);

        $this->assertRelayAuthException(
            function () use ($authHeader) {
                $this->httpAuth->verifyAuthHeader($authHeader);
            },
            RelayAuthException::EXPIRED_AUTH_HEADER,
            'Expired auth header should throw exception'
        );
    }

    /**
     * Test HTTP authentication with invalid signature
     */
    public function testHttpAuthInvalidSignature(): void
    {
        $this->logger->info('Testing HTTP authentication with invalid signature');

        $url = $this->getTestRelay('http') . '/api/event';
        $method = 'POST';
        $body = json_encode(['test' => 'data']);

        $authHeader = $this->httpAuth->createAuthHeader($url, $method, $body);
        $authHeader = str_replace('signature=', 'signature=invalid', $authHeader);

        $this->assertRelayAuthException(
            function () use ($authHeader) {
                $this->httpAuth->verifyAuthHeader($authHeader);
            },
            RelayAuthException::INVALID_SIGNATURE,
            'Invalid signature should throw exception'
        );
    }
} 
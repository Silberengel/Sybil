<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\RelayTests;

use Sybil\Tests\Integration\SybilIntegrationTestCase;
use Sybil\Utility\Relay\RelayAuth;
use Sybil\Utility\Relay\RelayAuthHTTP;
use Sybil\Exception\RelayAuthException;

/**
 * Base test case for relay authentication tests
 */
abstract class RelayAuthTestCase extends SybilIntegrationTestCase
{
    protected RelayAuth $wsAuth;
    protected RelayAuthHTTP $httpAuth;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize auth handlers
        $this->wsAuth = new RelayAuth($this->logger, $this->testPrivateKey);
        $this->httpAuth = new RelayAuthHTTP($this->logger, $this->testPrivateKey);
        
        $this->logger->info('Relay auth test environment initialized', [
            'pubkey' => $this->testPublicKey
        ]);
    }

    /**
     * Assert that an exception has the expected error code
     */
    protected function assertRelayAuthException(
        callable $callback,
        int $expectedCode,
        string $message = ''
    ): void {
        try {
            $callback();
            $this->fail('Expected RelayAuthException was not thrown');
        } catch (RelayAuthException $e) {
            $this->assertEquals(
                $expectedCode,
                $e->getCode(),
                $message ?: 'Unexpected error code in RelayAuthException'
            );
        }
    }
} 
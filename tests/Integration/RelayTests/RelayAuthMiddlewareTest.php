<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\RelayTests;

use Sybil\Middleware\RelayAuthMiddleware;
use Sybil\Exception\RelayAuthException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for relay authentication middleware
 */
final class RelayAuthMiddlewareTest extends RelayAuthTestCase
{
    private RelayAuthMiddleware $middleware;
    private array $excludedPaths = ['/public', '/health'];
    private array $excludedMethods = ['GET', 'HEAD', 'OPTIONS'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RelayAuthMiddleware(
            $this->logger,
            $this->httpAuth,
            $this->excludedPaths,
            $this->excludedMethods
        );
    }

    public function testExcludedPath(): void
    {
        $this->logger->info('Testing excluded path handling');
        
        $request = new ServerRequest('POST', '/public/event');
        $handler = $this->createMockHandler();
        
        $response = $this->middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testExcludedMethod(): void
    {
        $this->logger->info('Testing excluded method handling');
        
        $request = new ServerRequest('GET', '/event');
        $handler = $this->createMockHandler();
        
        $response = $this->middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMissingAuthHeader(): void
    {
        $this->logger->info('Testing missing auth header handling');
        
        $request = new ServerRequest('POST', '/event');
        $handler = $this->createMockHandler();
        
        $this->assertRelayAuthException(
            function () use ($request, $handler) {
                $this->middleware->process($request, $handler);
            },
            RelayAuthException::ERROR_MISSING_HEADER,
            'Expected missing header error'
        );
    }

    public function testInvalidAuthHeader(): void
    {
        $this->logger->info('Testing invalid auth header handling');
        
        $request = new ServerRequest('POST', '/event', [
            'Authorization' => 'Invalid Format'
        ]);
        $handler = $this->createMockHandler();
        
        $this->assertRelayAuthException(
            function () use ($request, $handler) {
                $this->middleware->process($request, $handler);
            },
            RelayAuthException::ERROR_INVALID_HEADER_FORMAT,
            'Expected invalid header format error'
        );
    }

    public function testSuccessfulAuthentication(): void
    {
        try {
            $this->logger->info('Testing successful middleware authentication');
            
            $relayUrl = $this->getTestRelay('http');
            $url = $relayUrl . '/event';
            $method = 'POST';
            $payload = json_encode(['content' => 'Test event']);
            
            // Create authentication header
            $authHeader = $this->httpAuth->createAuthHeader($url, $method, $payload);
            
            // Create request
            $request = new ServerRequest($method, $url, [
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json'
            ], $payload);
            
            $handler = $this->createMockHandler();
            
            $response = $this->middleware->process($request, $handler);
            $this->assertEquals(200, $response->getStatusCode());
            
            // Verify authenticated pubkey was added to request
            $this->assertEquals(
                $this->testPublicKey,
                $request->getAttribute('authenticated_pubkey')
            );
            
            $this->logger->info('Middleware authentication successful', [
                'pubkey' => $this->testPublicKey
            ]);
        } catch (RelayAuthException $e) {
            $this->logger->error('Middleware authentication failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail('Middleware authentication failed: ' . $e->getMessage());
        }
    }

    public function testExpiredAuthEvent(): void
    {
        $this->logger->info('Testing expired auth event handling');
        
        try {
            $relayUrl = $this->getTestRelay('http');
            $url = $relayUrl . '/event';
            $method = 'POST';
            
            // Create expired event
            $event = $this->httpAuth->createAuthEvent($url, $method);
            $event['created_at'] = time() - 3600; // 1 hour ago
            
            // Create auth header with expired event
            $authHeader = 'Nostr ' . base64_encode(json_encode($event));
            
            $request = new ServerRequest($method, $url, [
                'Authorization' => $authHeader
            ]);
            
            $handler = $this->createMockHandler();
            
            $this->assertRelayAuthException(
                function () use ($request, $handler) {
                    $this->middleware->process($request, $handler);
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

    private function createMockHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willReturn(new Response(200));
        return $handler;
    }
} 
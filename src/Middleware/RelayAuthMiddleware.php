<?php

namespace Sybil\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sybil\Utility\Relay\RelayAuthHTTP;
use Sybil\Utility\Relay\Exception\RelayAuthException;
use Psr\Log\LoggerInterface;

/**
 * Middleware for handling NIP-98 HTTP authentication
 */
class RelayAuthMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private RelayAuthHTTP $auth;
    private array $excludedPaths;
    private array $excludedMethods;

    /**
     * Create a new RelayAuthMiddleware
     *
     * @param LoggerInterface $logger The logger instance
     * @param RelayAuthHTTP $auth The authentication handler
     * @param array $excludedPaths Paths that don't require authentication
     * @param array $excludedMethods HTTP methods that don't require authentication
     */
    public function __construct(
        LoggerInterface $logger,
        RelayAuthHTTP $auth,
        array $excludedPaths = [],
        array $excludedMethods = ['GET', 'HEAD', 'OPTIONS']
    ) {
        $this->logger = $logger;
        $this->auth = $auth;
        $this->excludedPaths = $excludedPaths;
        $this->excludedMethods = array_map('strtoupper', $excludedMethods);
    }

    /**
     * Process the request
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The request handler
     * @return ResponseInterface The response
     * @throws RelayAuthException If authentication fails
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Check if path is excluded
            $path = $request->getUri()->getPath();
            if ($this->isPathExcluded($path)) {
                return $handler->handle($request);
            }

            // Check if method is excluded
            $method = strtoupper($request->getMethod());
            if (in_array($method, $this->excludedMethods)) {
                return $handler->handle($request);
            }

            $this->logger->debug('Processing authentication for request', [
                'path' => $path,
                'method' => $method
            ]);

            // Get Authorization header
            $authHeader = $request->getHeaderLine('Authorization');
            if (empty($authHeader)) {
                throw new RelayAuthException(
                    'Missing Authorization header',
                    RelayAuthException::ERROR_MISSING_HEADER
                );
            }

            // Parse Authorization header
            if (!preg_match('/^Nostr\s+(.+)$/', $authHeader, $matches)) {
                throw new RelayAuthException(
                    'Invalid Authorization header format',
                    RelayAuthException::ERROR_INVALID_HEADER_FORMAT
                );
            }

            // Decode and verify the authentication event
            $event = json_decode(base64_decode($matches[1]), true);
            if (!$event) {
                throw new RelayAuthException(
                    'Invalid authentication event data',
                    RelayAuthException::ERROR_INVALID_DATA
                );
            }

            // Get request body for payload verification
            $body = (string) $request->getBody();
            $payload = !empty($body) ? $body : null;

            // Verify the authentication event
            $isValid = $this->auth->verifyAuthEvent(
                $event,
                (string) $request->getUri(),
                $method,
                $payload
            );

            if (!$isValid) {
                throw new RelayAuthException(
                    'Authentication verification failed',
                    RelayAuthException::ERROR_VERIFICATION_FAILED
                );
            }

            // Add authenticated pubkey to request attributes
            $request = $request->withAttribute('authenticated_pubkey', $event['pubkey']);

            $this->logger->info('Request authenticated successfully', [
                'pubkey' => $event['pubkey'],
                'path' => $path,
                'method' => $method
            ]);

            return $handler->handle($request);
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during authentication', [
                'error' => $e->getMessage()
            ]);
            throw new RelayAuthException(
                'Authentication processing failed: ' . $e->getMessage(),
                RelayAuthException::ERROR_MIDDLEWARE_PROCESS,
                $e
            );
        }
    }

    /**
     * Check if a path is excluded from authentication
     *
     * @param string $path The request path
     * @return bool True if the path is excluded
     */
    private function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (strpos($path, $excludedPath) === 0) {
                return true;
            }
        }
        return false;
    }
} 
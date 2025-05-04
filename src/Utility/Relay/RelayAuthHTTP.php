<?php

namespace Sybil\Utility\Relay;

use Sybil\Utility\Key\KeyPair;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Utility\Relay\Exception\RelayAuthException;
use Sybil\Utility\Event\EventPreparation;
use Psr\Log\LoggerInterface;

/**
 * Handles HTTP authentication with Nostr servers.
 * Implements NIP-98 for HTTP authentication.
 */
class RelayAuthHTTP
{
    private LoggerInterface $logger;
    private KeyPair $keyPair;
    private int $timeWindow = 60; // seconds

    public function __construct(LoggerInterface $logger, ?string $privateKey = null)
    {
        $this->logger = $logger;
        $this->keyPair = new KeyPair($privateKey ?? KeyUtility::getPrivateKey());
    }

    /**
     * Create an authentication event for an HTTP request
     *
     * @param string $url The absolute URL
     * @param string $method The HTTP method
     * @param string|null $payload Optional request body for hashing
     * @return array The authentication event
     * @throws RelayAuthException If event creation fails
     */
    public function createAuthEvent(string $url, string $method, ?string $payload = null): array
    {
        try {
            $this->logger->debug('Creating HTTP authentication event', [
                'url' => $url,
                'method' => $method
            ]);

            // Create event tags
            $tags = [
                ['u', $url],
                ['method', strtoupper($method)]
            ];

            // Add payload hash if provided
            if ($payload !== null) {
                $tags[] = ['payload', hash('sha256', $payload)];
            }

            // Create and sign the event
            $event = EventPreparation::createAndSignEvent(
                27235, // NIP-98 event kind
                '',
                $tags,
                $this->keyPair->getPrivateKey()
            );

            $this->logger->info('HTTP authentication event created', [
                'url' => $url,
                'method' => $method,
                'event_id' => $event->getId()
            ]);

            return $event->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to create HTTP authentication event', [
                'error' => $e->getMessage()
            ]);
            throw new RelayAuthException(
                'Failed to create HTTP authentication event: ' . $e->getMessage(),
                RelayAuthException::ERROR_EVENT_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Create an Authorization header for an HTTP request
     *
     * @param string $url The absolute URL
     * @param string $method The HTTP method
     * @param string|null $payload Optional request body for hashing
     * @return string The Authorization header value
     * @throws RelayAuthException If header creation fails
     */
    public function createAuthHeader(string $url, string $method, ?string $payload = null): string
    {
        try {
            $event = $this->createAuthEvent($url, $method, $payload);
            $encoded = base64_encode(json_encode($event));
            return 'Nostr ' . $encoded;
        } catch (\Exception $e) {
            throw new RelayAuthException(
                'Failed to create Authorization header: ' . $e->getMessage(),
                RelayAuthException::ERROR_HEADER_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Verify an HTTP authentication event
     *
     * @param array $event The authentication event
     * @param string $url The request URL
     * @param string $method The HTTP method
     * @param string|null $payload Optional request body for verification
     * @return bool True if the event is valid
     * @throws RelayAuthException If verification fails
     */
    public function verifyAuthEvent(array $event, string $url, string $method, ?string $payload = null): bool
    {
        try {
            $this->logger->debug('Verifying HTTP authentication event', [
                'url' => $url,
                'method' => $method,
                'event_id' => $event['id'] ?? 'unknown'
            ]);

            // Check event kind
            if (!isset($event['kind']) || $event['kind'] !== 27235) {
                throw new RelayAuthException(
                    'Invalid event kind for HTTP authentication',
                    RelayAuthException::ERROR_INVALID_EVENT_KIND
                );
            }

            // Check timestamp
            if (!isset($event['created_at'])) {
                throw new RelayAuthException(
                    'Missing created_at timestamp',
                    RelayAuthException::ERROR_INVALID_TIMESTAMP
                );
            }

            $age = time() - $event['created_at'];
            if ($age > $this->timeWindow || $age < 0) {
                throw new RelayAuthException(
                    "Event timestamp is outside the allowed time window ($age seconds)",
                    RelayAuthException::ERROR_TIMESTAMP_EXPIRED
                );
            }

            // Check URL tag
            if (!isset($event['tags']) || !$this->hasTag($event['tags'], 'u', $url)) {
                throw new RelayAuthException(
                    'URL tag does not match request URL',
                    RelayAuthException::ERROR_URL_MISMATCH
                );
            }

            // Check method tag
            if (!isset($event['tags']) || !$this->hasTag($event['tags'], 'method', strtoupper($method))) {
                throw new RelayAuthException(
                    'Method tag does not match request method',
                    RelayAuthException::ERROR_METHOD_MISMATCH
                );
            }

            // Check payload hash if provided
            if ($payload !== null) {
                $expectedHash = hash('sha256', $payload);
                if (!isset($event['tags']) || !$this->hasTag($event['tags'], 'payload', $expectedHash)) {
                    throw new RelayAuthException(
                        'Payload hash does not match request body',
                        RelayAuthException::ERROR_PAYLOAD_MISMATCH
                    );
                }
            }

            // Verify event signature
            if (!isset($event['pubkey']) || !isset($event['sig'])) {
                throw new RelayAuthException(
                    'Missing pubkey or signature',
                    RelayAuthException::ERROR_INVALID_SIGNATURE
                );
            }

            $isValid = KeyPair::verify(
                $this->getEventHash($event),
                $event['sig'],
                $event['pubkey']
            );

            if (!$isValid) {
                throw new RelayAuthException(
                    'Invalid event signature',
                    RelayAuthException::ERROR_INVALID_SIGNATURE
                );
            }

            $this->logger->info('HTTP authentication event verified', [
                'url' => $url,
                'method' => $method,
                'event_id' => $event['id']
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('HTTP authentication verification failed', [
                'error' => $e->getMessage()
            ]);
            throw new RelayAuthException(
                'Failed to verify HTTP authentication: ' . $e->getMessage(),
                RelayAuthException::ERROR_VERIFICATION_FAILED,
                $e
            );
        }
    }

    /**
     * Check if an event has a specific tag
     *
     * @param array $tags The event tags
     * @param string $name The tag name
     * @param string $value The tag value
     * @return bool True if the tag exists with the given value
     */
    private function hasTag(array $tags, string $name, string $value): bool
    {
        foreach ($tags as $tag) {
            if ($tag[0] === $name && $tag[1] === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the event hash for signature verification
     *
     * @param array $event The event data
     * @return string The event hash
     */
    private function getEventHash(array $event): string
    {
        $data = [
            0,
            $event['pubkey'],
            $event['created_at'],
            $event['kind'],
            $event['tags'],
            $event['content']
        ];
        return hash('sha256', json_encode($data));
    }

    /**
     * Set the time window for event validation
     *
     * @param int $seconds The time window in seconds
     */
    public function setTimeWindow(int $seconds): void
    {
        $this->timeWindow = max(1, $seconds);
    }
} 
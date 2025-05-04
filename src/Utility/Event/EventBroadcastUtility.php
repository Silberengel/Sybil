<?php

namespace Sybil\Utility\Event;

use Sybil\Entity\NostrEvent;
use Sybil\Service\RelayQueryService;
use Sybil\Exception\EventPublishException;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\RelayConnectionException;
use Psr\Log\LoggerInterface;
use Sybil\Utility\Log\LoggerFactory;
use swentel\nostr\Event\Event;
use Exception;

/**
 * Utility class for broadcasting Nostr events
 * 
 * This class provides utility functions for broadcasting Nostr events to relays,
 * including retry logic and result handling.
 */
class EventBroadcastUtility
{
    private RelayQueryService $relayQueryService;
    private LoggerInterface $logger;
    private const MAX_CONTENT_LOG_LENGTH = 100;

    public function __construct(RelayQueryService $relayQueryService)
    {
        $this->relayQueryService = $relayQueryService;
        $this->logger = LoggerFactory::createLogger('event_broadcast');
    }

    /**
     * Broadcast an event to relays
     *
     * @param Event|NostrEvent $event The event to broadcast
     * @param array $relayUrls Optional array of relay URLs to use instead of the default list
     * @return bool True if the event was broadcast successfully, false otherwise
     * @throws EventPublishException If event publishing fails
     * @throws RelayAuthException If authentication fails
     * @throws RelayConnectionException If relay connection fails
     */
    public function broadcast(Event|NostrEvent $event, array $relayUrls = []): bool
    {
        if (!$event->getId()) {
            throw new EventPublishException(
                'Cannot broadcast event without ID',
                EventPublishException::ERROR_INVALID_EVENT,
                null,
                ['kind' => $event->getKind()]
            );
        }

        $this->logger->debug('Starting event broadcast', [
            'event_id' => $event->getId(),
            'relay_count' => count($relayUrls),
            'kind' => $event->getKind(),
            'pubkey' => $this->sanitizePubkey($event->getPubkey()),
            'content_length' => strlen($event->getContent()),
            'relay_urls' => $this->sanitizeRelayUrls($relayUrls)
        ]);

        try {
            if ($event instanceof NostrEvent) {
                // Convert to swentel event
                $nostrEvent = new Event();
                $nostrEvent->setId($event->getId());
                $nostrEvent->setPubkey($event->getPubkey());
                $nostrEvent->setCreatedAt($event->getCreatedAt());
                $nostrEvent->setKind($event->getKind());
                $nostrEvent->setContent($event->getContent());
                $nostrEvent->setTags($event->getTags());
                $nostrEvent->setSig($event->getSig());
                $event = $nostrEvent;
            }

            $this->logger->info('Broadcasting event', [
                'event_id' => $event->getId(),
                'kind' => $event->getKind(),
                'pubkey' => $this->sanitizePubkey($event->getPubkey()),
                'content_preview' => $this->sanitizeContent($event->getContent()),
                'tag_count' => count($event->getTags()),
                'relay_count' => count($relayUrls)
            ]);
            
            $this->relayQueryService->publishEvent($event, $relayUrls);
            
            $this->logger->info('Event broadcast successful', [
                'event_id' => $event->getId(),
                'relay_count' => count($relayUrls),
                'kind' => $event->getKind()
            ]);
            
            return true;
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed during broadcast', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'relay_url' => $e->getRelayUrl(),
                'pubkey' => $this->sanitizePubkey($event->getPubkey()),
                'kind' => $event->getKind()
            ]);
            throw $e;
        } catch (RelayConnectionException $e) {
            $this->logger->error('Relay connection failed during broadcast', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'relay_url' => $e->getRelayUrl(),
                'pubkey' => $this->sanitizePubkey($event->getPubkey()),
                'kind' => $event->getKind()
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during broadcast', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'pubkey' => $this->sanitizePubkey($event->getPubkey()),
                'kind' => $event->getKind(),
                'relay_count' => count($relayUrls)
            ]);
            throw new EventPublishException(
                'Failed to broadcast event: ' . $e->getMessage(),
                EventPublishException::ERROR_BROADCAST_FAILED,
                $e,
                [
                    'event_id' => $event->getId(),
                    'kind' => $event->getKind(),
                    'pubkey' => $this->sanitizePubkey($event->getPubkey()),
                    'relay_count' => count($relayUrls)
                ],
                $event->getId()
            );
        }
    }

    /**
     * Sanitize a public key for logging
     *
     * @param string $pubkey The public key to sanitize
     * @return string The sanitized public key
     */
    private function sanitizePubkey(string $pubkey): string
    {
        return substr($pubkey, 0, 8) . '...';
    }

    /**
     * Sanitize event content for logging
     *
     * @param string $content The content to sanitize
     * @return string The sanitized content
     */
    private function sanitizeContent(string $content): string
    {
        if (strlen($content) > self::MAX_CONTENT_LOG_LENGTH) {
            return substr($content, 0, self::MAX_CONTENT_LOG_LENGTH) . '...';
        }
        return $content;
    }

    /**
     * Sanitize relay URLs for logging
     *
     * @param array $relayUrls The relay URLs to sanitize
     * @return array The sanitized relay URLs
     */
    private function sanitizeRelayUrls(array $relayUrls): array
    {
        return array_map(function($url) {
            return parse_url($url, PHP_URL_HOST) ?: $url;
        }, $relayUrls);
    }
} 
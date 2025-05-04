<?php

namespace Sybil\Service;

use Sybil\Entity\NostrEvent;
use Sybil\Service\RelayQueryService;
use Sybil\Exception\EventPublishException;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\RelayConnectionException;
use Sybil\Utility\Log\LoggerFactory;
use Psr\Log\LoggerInterface;
use swentel\nostr\Event\Event;
use Sybil\Utility\Relay\RelayAuthHTTP;
use Sybil\Utility\Key\KeyPair;

/**
 * Service class for broadcasting Nostr events
 * 
 * This service handles broadcasting Nostr events to relays using WebSocket connections
 * and HTTP authentication when required.
 */
class EventBroadcastService
{
    private RelayQueryService $relayQueryService;
    private LoggerInterface $logger;
    private ?RelayAuthHTTP $authHandler = null;
    private ?KeyPair $keyPair = null;

    public function __construct(
        RelayQueryService $relayQueryService,
        ?KeyPair $keyPair = null
    ) {
        $this->relayQueryService = $relayQueryService;
        $this->logger = LoggerFactory::createLogger('event_broadcast_service');
        $this->keyPair = $keyPair;
        
        if ($keyPair) {
            $this->authHandler = new RelayAuthHTTP($keyPair->getPrivateKey());
        }
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
        $this->logger->debug('Starting event broadcast', [
            'event_id' => $event->getId(),
            'relay_count' => count($relayUrls)
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
                'pubkey' => substr($event->getPubkey(), 0, 8) . '...' // Only log first 8 chars of pubkey
            ]);
            
            // Add authentication if available
            if ($this->authHandler) {
                $this->logger->debug('Adding authentication to broadcast', [
                    'event_id' => $event->getId()
                ]);
                $this->relayQueryService->setAuthHandler($this->authHandler);
            }
            
            $this->relayQueryService->publishEvent($event, $relayUrls);
            
            $this->logger->info('Event broadcast successful', [
                'event_id' => $event->getId()
            ]);
            
            return true;
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed during broadcast', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'relay_url' => $e->getRelayUrl()
            ]);
            throw $e;
        } catch (RelayConnectionException $e) {
            $this->logger->error('Relay connection failed during broadcast', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'relay_url' => $e->getRelayUrl()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during broadcast', [
                'event_id' => $event->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventPublishException(
                'Failed to broadcast event: ' . $e->getMessage(),
                EventPublishException::ERROR_BROADCAST_FAILED,
                $e,
                ['event_id' => $event->getId()],
                $event->getId()
            );
        }
    }

    /**
     * Set the key pair for authentication
     *
     * @param KeyPair $keyPair The key pair to use for authentication
     * @return void
     */
    public function setKeyPair(KeyPair $keyPair): void
    {
        $this->keyPair = $keyPair;
        $this->authHandler = new RelayAuthHTTP($keyPair->getPrivateKey());
        $this->logger->debug('Key pair updated for authentication');
    }

    /**
     * Get the current key pair
     *
     * @return KeyPair|null The current key pair or null if not set
     */
    public function getKeyPair(): ?KeyPair
    {
        return $this->keyPair;
    }
} 
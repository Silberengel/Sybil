<?php

namespace Sybil\Service;

use Sybil\Entity\NostrEventEntity;
use Sybil\Exception\EventCreationException;
use Sybil\Exception\RelayAuthException;
use Sybil\Utility\Log\LoggerFactory;
use Psr\Log\LoggerInterface;
use Sybil\Utility\Key\KeyPair;
use Sybil\Utility\Event\EventPreparation;

/**
 * Service class for creating Nostr events
 * 
 * This service handles the creation and signing of Nostr events,
 * including proper authentication and error handling.
 */
class EventFactoryService
{
    private LoggerInterface $logger;
    private ?KeyPair $keyPair = null;
    private EventPreparation $eventPreparation;

    public function __construct(?KeyPair $keyPair = null)
    {
        $this->logger = LoggerFactory::createLogger('event_factory');
        $this->keyPair = $keyPair;
        $this->eventPreparation = new EventPreparation();
    }

    /**
     * Create a new Nostr event
     *
     * @param int $kind The event kind
     * @param string $content The event content
     * @param array $tags Optional array of event tags
     * @return NostrEvent The created and signed event
     * @throws EventCreationException If event creation fails
     * @throws RelayAuthException If authentication fails
     */
    public function createEvent(int $kind, string $content, array $tags = []): NostrEvent
    {
        $this->logger->debug('Creating new event', [
            'kind' => $kind,
            'content_length' => strlen($content),
            'tags_count' => count($tags)
        ]);

        try {
            if (!$this->keyPair) {
                throw new EventCreationException(
                    'No key pair available for event creation',
                    EventCreationException::ERROR_NO_KEY_PAIR
                );
            }

            $event = new NostrEvent();
            $event->setKind($kind);
            $event->setContent($content);
            $event->setTags($tags);
            $event->setPubkey($this->keyPair->getPublicKey());
            $event->setCreatedAt(time());

            $this->logger->debug('Event created, preparing for signing', [
                'event_id' => $event->getId(),
                'pubkey' => substr($event->getPubkey(), 0, 8) . '...' // Only log first 8 chars of pubkey
            ]);

            // Prepare and sign the event
            $preparedEvent = $this->eventPreparation->prepareEvent($event);
            $signedEvent = $this->eventPreparation->signEvent($preparedEvent, $this->keyPair->getPrivateKey());

            $this->logger->info('Event created and signed successfully', [
                'event_id' => $signedEvent->getId(),
                'kind' => $signedEvent->getKind()
            ]);

            return $signedEvent;
        } catch (EventCreationException $e) {
            $this->logger->error('Event creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during event creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventCreationException(
                'Failed to create event: ' . $e->getMessage(),
                EventCreationException::ERROR_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Set the key pair for event signing
     *
     * @param KeyPair $keyPair The key pair to use for signing
     * @return void
     */
    public function setKeyPair(KeyPair $keyPair): void
    {
        $this->keyPair = $keyPair;
        $this->logger->debug('Key pair updated for event signing');
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

    /**
     * Create a text note event (kind 1)
     *
     * @param string $content The note content
     * @param array $tags Optional array of event tags
     * @return NostrEvent The created and signed note event
     * @throws EventCreationException If event creation fails
     * @throws RelayAuthException If authentication fails
     */
    public function createTextNote(string $content, array $tags = []): NostrEvent
    {
        return $this->createEvent(1, $content, $tags);
    }

    /**
     * Create a metadata event (kind 0)
     *
     * @param array $metadata The metadata content
     * @return NostrEvent The created and signed metadata event
     * @throws EventCreationException If event creation fails
     * @throws RelayAuthException If authentication fails
     */
    public function createMetadata(array $metadata): NostrEvent
    {
        return $this->createEvent(0, json_encode($metadata));
    }
} 
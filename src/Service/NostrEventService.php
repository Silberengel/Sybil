<?php

namespace Sybil\Service;

use Psr\Log\LoggerInterface;
use Sybil\Entity\NostrEvent;
use Sybil\Exception\EventPublishException;
use Sybil\Exception\ValidationException;
use Sybil\Repository\NostrEventRepository;
use Sybil\Utility\Event\EventBroadcastUtility;
use Sybil\Utility\Event\EventPreparation;
use swentel\nostr\Event\Event;
use Sybil\Factory\EventFactory;
use Sybil\Service\RelayQueryService;
use Sybil\Utility\Log\LoggerFactory;

class NostrEventService
{
    private LoggerInterface $logger;
    private NostrEventRepository $eventRepository;
    private EventBroadcastUtility $eventBroadcast;
    private EventFactory $eventFactory;
    private RelayQueryService $relayService;

    public function __construct(
        NostrEventRepository $eventRepository,
        EventBroadcastUtility $eventBroadcast,
        EventFactory $eventFactory,
        RelayQueryService $relayService
    ) {
        $this->logger = LoggerFactory::createLogger('nostr_event_service');
        $this->eventRepository = $eventRepository;
        $this->eventBroadcast = $eventBroadcast;
        $this->eventFactory = $eventFactory;
        $this->relayService = $relayService;
    }

    /**
     * Create a new Nostr event
     *
     * @param array $data The event data
     * @return NostrEvent The created event
     * @throws ValidationException If event validation fails
     */
    public function createEvent(array $data): NostrEvent
    {
        try {
            $event = new NostrEvent();
            $event->setId($data['id'] ?? '');
            $event->setPubkey($data['pubkey'] ?? '');
            $event->setCreatedAt($data['created_at'] ?? time());
            $event->setKind($data['kind'] ?? 0);
            $event->setContent($data['content'] ?? '');
            $event->setTags($data['tags'] ?? []);
            $event->setSig($data['sig'] ?? '');

            return $event;
        } catch (\Exception $e) {
            throw new ValidationException('Failed to create event: ' . $e->getMessage());
        }
    }

    /**
     * Publish a Nostr event to relays
     *
     * @param NostrEvent|Event $event The event to publish
     * @param array $relayUrls Optional array of relay URLs to use
     * @return bool True if the event was published successfully
     * @throws EventPublishException If event publishing fails
     */
    public function publishEvent(NostrEvent|Event $event, array $relayUrls = []): bool
    {
        try {
            // Broadcast the event
            $success = $this->eventBroadcast->broadcast($event, $relayUrls);

            if ($success && $event instanceof NostrEvent) {
                // Save to database
                $event->setPublished(true);
                $event->setPublishedAt(new \DateTimeImmutable());
                $this->eventRepository->save($event);
            }

            return $success;
        } catch (\Exception $e) {
            throw new EventPublishException('Failed to publish event: ' . $e->getMessage());
        }
    }

    /**
     * Get an event by ID
     *
     * @param string $eventId The event ID
     * @return NostrEvent|null The event if found, null otherwise
     */
    public function getEvent(string $eventId): ?NostrEvent
    {
        return $this->eventRepository->find($eventId);
    }

    /**
     * Create a new note event
     *
     * @param string $content The note content
     * @param string $privateKey The private key to sign the note
     * @return Event The created note event
     * @throws \InvalidArgumentException If content or private key is empty
     */
    public function createNote(string $content, string $privateKey): Event
    {
        if (empty($content)) {
            throw new \InvalidArgumentException('Note content cannot be empty');
        }

        if (empty($privateKey)) {
            throw new \InvalidArgumentException('Private key is required');
        }

        return $this->eventFactory->createNote($content, $privateKey);
    }

    /**
     * Publish an event to relays
     *
     * @param Event $event The event to publish
     * @throws \InvalidArgumentException If event is null
     */
    public function publish(Event $event): void
    {
        if ($event === null) {
            throw new \InvalidArgumentException('Event cannot be null');
        }

        $this->relayService->publish($event);
    }

    /**
     * Query events from relays
     *
     * @param array $filter The filter to use
     * @return array The matching events
     * @throws \InvalidArgumentException If filter is empty or invalid
     */
    public function query(array $filter): array
    {
        if (empty($filter)) {
            throw new \InvalidArgumentException('Filter cannot be empty');
        }

        if (!isset($filter['kinds']) && !isset($filter['authors']) && !isset($filter['tags'])) {
            throw new \InvalidArgumentException('Invalid filter format');
        }

        return $this->relayService->query($filter);
    }
} 
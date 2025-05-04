<?php

namespace Sybil\Utility\Event;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use Sybil\Exception\EventException;

/**
 * Utility class for preparing Nostr events
 * 
 * This class provides utility functions for preparing Nostr events,
 * including signing, creating event messages, and preparing event data.
 */
class EventPreparation
{
    /**
     * Create and sign an event
     *
     * @param int $kind The event kind
     * @param string $content The event content
     * @param array $tags The event tags
     * @param string|null $privateKey Optional private key for signing
     * @return Event The created and signed event
     * @throws EventException If event creation or signing fails
     */
    public static function createAndSignEvent(
        int $kind,
        string $content,
        array $tags = [],
        ?string $privateKey = null
    ): Event {
        try {
            $event = new Event();
            $event->setKind($kind);
            $event->setContent($content);
            $event->setTags($tags);
            
            if ($privateKey) {
                $sign = new Sign($privateKey);
                $event->setSign($sign);
            }
            
            return $event;
        } catch (\Exception $e) {
            throw new EventException('Failed to create and sign event: ' . $e->getMessage());
        }
    }

    /**
     * Create an event message
     *
     * @param Event $event The event to create a message for
     * @return EventMessage The created event message
     * @throws EventException If message creation fails
     */
    public static function createEventMessage(Event $event): EventMessage
    {
        try {
            return new EventMessage($event);
        } catch (\Exception $e) {
            throw new EventException('Failed to create event message: ' . $e->getMessage());
        }
    }

    /**
     * Create a deletion event
     *
     * @param string $eventId The ID of the event to delete
     * @param int $kind The event kind
     * @param string $relayUrl The relay URL
     * @param string|null $privateKey Optional private key for signing
     * @return Event The deletion event
     * @throws EventException If deletion event creation fails
     */
    public static function createDeletionEvent(
        string $eventId,
        int $kind,
        string $relayUrl,
        ?string $privateKey = null
    ): Event {
        try {
            $tags = [
                ['e', $eventId],
                ['relay', $relayUrl]
            ];
            
            return self::createAndSignEvent(
                5, // NIP-09 deletion event kind
                'Deletion request',
                $tags,
                $privateKey
            );
        } catch (\Exception $e) {
            throw new EventException('Failed to create deletion event: ' . $e->getMessage());
        }
    }

    /**
     * Create a delegation tag for NIP-26
     *
     * @param string $delegateePubkey The public key of the delegatee
     * @param int $since The timestamp from which delegation is valid
     * @param int $until The timestamp until which delegation is valid
     * @param string $conditions Optional conditions for the delegation
     * @return array The delegation tag
     */
    public static function createDelegationTag(
        string $delegateePubkey,
        int $since,
        int $until,
        string $conditions = ''
    ): array {
        return [
            'delegation',
            $delegateePubkey,
            $conditions,
            $since,
            $until
        ];
    }
} 
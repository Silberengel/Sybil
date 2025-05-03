<?php
/**
 * Class EventPreparationUtility
 * 
 * This class provides utility functions for preparing Nostr events:
 * - Signing events
 * - Creating event messages
 * - Preparing event data
 * - Creating and signing events
 * - Creating deletion events
 * - Extracting event data from relay responses
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\EventPreparationUtility;
 * use swentel\nostr\Event\Event;
 * 
 * // Create and sign an event
 * $event = EventPreparationUtility::createAndSignEvent(1, 'Hello, world!', [['t', 'test']]);
 * 
 * // Create an event message
 * $eventMessage = EventPreparationUtility::createEventMessage($event);
 * 
 * // Prepare event data and send it to relays
 * $result = EventPreparationUtility::prepareEventData($event);
 * 
 * // Create a deletion event
 * $deletionEvent = EventPreparationUtility::createDeletionEvent('event-id', 1, 'wss://relay.example.com');
 * 
 * // Extract event data from a relay response
 * $eventData = EventPreparationUtility::extractEventData($response, 'event-id');
 * 
 * // Create an event object from event data
 * $event = EventPreparationUtility::createEventFromData($eventData);
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see EventUtility For event-related operations
 * @see KeyUtility For key-related operations
 * @see RelayUtility For relay-related operations
 */

namespace Sybil\Utilities;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;

class EventPreparationUtility
{
    /**
     * Signs the note, reads the relays from relays.yml and sends the event.
     *
     * @param Event $note The event to sign and send
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @return array The result from sending the event
     * @throws \InvalidArgumentException If the private key is missing or invalid
     * @throws \TypeError If there's an issue sending to the relay
     */
    public static function prepareEventData(Event $note, ?string $keyEnvVar = null): array
    {
        // Get private key from environment
        $privateKey = KeyUtility::getNsec($keyEnvVar);
        
        // Get the event kind
        $kind = 0;
        if (method_exists($note, 'getKind')) {
            $kind = $note->getKind();
        }
        
        // Get relay list for this kind of event, with authentication enabled
        $relays = RelayUtility::getRelayList($kind, [], true);
        
        // Sign the event
        $signer = new Sign();
        $signer->signEvent($note, $privateKey);
        
        // Create event message
        $eventMessage = new EventMessage($note);
        
        // Send the event with retry on failure, passing the custom relay list
        return RelayUtility::sendEventWithRetry($eventMessage, $relays);
    }
    
    /**
     * Creates and signs an event.
     *
     * @param int $kind The event kind
     * @param string $content The event content
     * @param array $tags The event tags
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @return Event The created and signed event
     */
    public static function createAndSignEvent(int $kind, string $content, array $tags = [], ?string $keyEnvVar = null): Event
    {
        // Get private key from environment
        $privateKey = KeyUtility::getNsec($keyEnvVar);
        
        // Create the event
        $event = new Event();
        $event->setKind($kind);
        $event->setContent($content);
        $event->setTags($tags);
        
        // Sign the event
        $signer = new Sign();
        $signer->signEvent($event, $privateKey);
        
        return $event;
    }
    
    /**
     * Creates an event message from an event.
     *
     * @param Event $event The event to create a message from
     * @return EventMessage The created event message
     */
    public static function createEventMessage(Event $event): EventMessage
    {
        return new EventMessage($event);
    }
    
    /**
     * Creates a deletion event for an existing event.
     *
     * @param string $eventId The ID of the event to delete
     * @param int $eventKind The kind of the event to delete
     * @param string $referenceRelay The relay where the event was found
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @return Event The created deletion event
     */
    public static function createDeletionEvent(string $eventId, int $eventKind, string $referenceRelay, ?string $keyEnvVar = null): Event
    {
        // Create deletion event
        $note = new Event();
        $note->setKind(5); // 5 is the deletion event kind
        
        $note->setTags([
            ['e', $eventId, $referenceRelay],
            ['k', $eventKind]
        ]);
        
        // Sign the deletion event
        $privateKey = KeyUtility::getNsec($keyEnvVar);
        $signer = new Sign();
        $signer->signEvent($note, $privateKey);
        
        return $note;
    }
    
    /**
     * Extracts event data from a relay response.
     *
     * @param array $response The relay response
     * @param string $eventId The event ID to extract
     * @return array|null The extracted event data, or null if not found
     */
    public static function extractEventData(array $response, string $eventId): ?array
    {
        return RequestUtility::extractEventData($response, $eventId);
    }
    
    /**
     * Creates an event object from event data.
     *
     * @param array $eventData The event data
     * @return Event The created event object
     */
    public static function createEventFromData(array $eventData): Event
    {
        $event = new Event();
        
        if (isset($eventData['id'])) {
            $event->setId($eventData['id']);
        }
        
        if (isset($eventData['kind'])) {
            $event->setKind($eventData['kind']);
        }
        
        if (isset($eventData['content'])) {
            $event->setContent($eventData['content']);
        }
        
        if (isset($eventData['tags'])) {
            $event->setTags($eventData['tags']);
        }
        
        return $event;
    }

    /**
     * Creates a delegation tag for NIP-26
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

    /**
     * Creates and signs an event with delegation
     *
     * @param int $kind The event kind
     * @param string $content The event content
     * @param array $tags The event tags
     * @param string $delegateePubkey The public key of the delegatee
     * @param int $since The timestamp from which delegation is valid
     * @param int $until The timestamp until which delegation is valid
     * @param string $conditions Optional conditions for the delegation
     * @return Event The signed event
     */
    public static function createAndSignDelegatedEvent(
        int $kind,
        string $content,
        array $tags,
        string $delegateePubkey,
        int $since,
        int $until,
        string $conditions = ''
    ): Event {
        // Add delegation tag
        $tags[] = self::createDelegationTag($delegateePubkey, $since, $until, $conditions);
        
        // Create and sign the event
        return self::createAndSignEvent($kind, $content, $tags);
    }
}

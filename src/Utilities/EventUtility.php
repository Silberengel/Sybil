<?php
/**
 * Class EventUtility
 * 
 * This class provides utility functions for working with Nostr events:
 * - Fetching events from relays
 * - Broadcasting events to relays
 * - Deleting events from relays
 * 
 * It follows object-oriented principles with encapsulated properties
 * and clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\EventUtility;
 * 
 * // Create an event utility with an event ID
 * $eventUtility = new EventUtility('event-id');
 * 
 * // Fetch an event
 * list($fetchedEvent, $relaysWithEvent) = $eventUtility->fetch_event();
 * 
 * // Broadcast an event
 * $result = $eventUtility->broadcast_event();
 * 
 * // Delete an event
 * $result = $eventUtility->delete_event();
 * 
 * // Run a utility function based on a command
 * $result = $eventUtility->run_utility('fetch');
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see RelayUtility For relay-related operations
 * @see RequestUtility For request-related operations
 * @see KeyUtility For key-related operations
 * @see EventPreparationUtility For event preparation operations
 */

namespace Sybil\Utilities;

use Sybil\Exception\RecordNotFoundException;
use Sybil\Service\LoggerService;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Event\Event;
use InvalidArgumentException;
use Exception;

class EventUtility
{
    // Properties
    private string $eventID = '';
    /**
     * @var LoggerService Logger service
     */
    protected LoggerService $logger;
    
    // Constants
    public const DEFAULT_RELAY = 'wss://freelay.sovbit.host';
    private const MAX_RETRIES = 2;

    /**
     * Constructor for EventUtility
     * 
     * @param string $eventID Optional event ID to initialize with
     * @param LoggerService|null $logger Logger service
     */
    public function __construct(string $eventID = '', ?LoggerService $logger = null)
    {
        if (!empty($eventID)) {
            $this->setEventID($eventID);
        }
        $this->logger = $logger ?? new LoggerService();
    }
    
    /**
     * Get the event ID
     * 
     * @return string The event ID
     */
    public function getEventID(): string
    {
        return $this->eventID;
    }
    
    /**
     * Set the event ID
     * 
     * @param string $eventID The event ID
     * @return self
     */
    public function setEventID(string $eventID): self
    {
        $this->eventID = $eventID;
        return $this;
    }

    /**
     * Run a utility function based on the command
     * 
     * @param string $command The command to run (fetch, broadcast, delete)
     * @return array{success: bool, message: string, event_id?: string, successful_relays?: array<string>, failed_relays?: array<string>} The result of the utility function
     * @throws InvalidArgumentException If the command is invalid
     */
    public function run_utility(string $command): array
    {
        switch($command) {
            case 'fetch':
                list($result, $relaysWithEvent) = $this->fetch_event();
                
                if (!empty($relaysWithEvent)) {
                    $this->logger->output("Event found on " . count($relaysWithEvent) . " relays: ");
                    $this->logger->output(implode(", ", $relaysWithEvent));
                } else {
                    $this->logger->warning("Event not found on any relay.");
                }
                
                return $result;
                
            case 'broadcast':
                return $this->broadcast_event();
                
            case 'delete':
                return $this->delete_event();
                
            default:
                throw new InvalidArgumentException(
                    'That is not a valid command.');
        }
    }

    /**
     * Fetch an event from relays using the hex ID
     * 
     * @return array{0: array<string, mixed>, 1: array<string>} An array containing [0] the fetched event data from the first successful relay and [1] an array of relay URLs that have the event
     * @throws InvalidArgumentException If the event ID is not set
     */
    public function fetch_event(): array
    {
        if (empty($this->eventID)) {
            throw new InvalidArgumentException("Event ID must be set before fetching");
        }

        $this->logger->info("Fetching event {$this->eventID} from relays...");
        
        $eventIDs[] = $this->eventID;

        $filter = new Filter();
        $filter->setIds($eventIDs);
        $filters = [$filter];

        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getid(), $filters);
        
        // Use multiple relays instead of just the default relay
        $relays = [
            new Relay('wss://freelay.sovbit.host'),
            new Relay('wss://relay.damus.io'),
            new Relay('wss://relay.nostr.band'),
            new Relay('wss://nostr.einundzwanzig.space'),
            new Relay('wss://relay.primal.net'),
            new Relay('wss://nos.lol'),
            new Relay('wss://relay.lumina.rocks'),
            new Relay('wss://wheat.happytavern.co'),
            new Relay('wss://nostr21.com'),
            new Relay('wss://theforest.nostr1.com')
        ];
        
        $relaysWithEvent = [];
        $firstResult = null;
        $failedRelays = [];
        
        // Check all relays to find which ones have the event
        foreach ($relays as $relay) {
            $relayUrl = $relay->getUrl();
            $this->logger->info("Checking relay: {$relayUrl}");
            
            try {
                $result = RequestUtility::sendWithRetry($relay, $requestMessage, self::MAX_RETRIES);
                
                // Check if this relay has the event
                if (is_array($result) && RequestUtility::responseContainsEvent($result, $this->eventID)) {
                    $this->logger->info("Event found on relay: {$relayUrl}");
                    $relaysWithEvent[] = $relayUrl;
                    
                    // Save the first result we find
                    if ($firstResult === null) {
                        $singleRelayResult = [];
                        $singleRelayResult[$relayUrl] = $result[$relayUrl];
                        $firstResult = $singleRelayResult;
                    }
                } else {
                    $this->logger->info("Event not found on relay: {$relayUrl}");
                }
            } catch (Exception $e) {
                $failedRelays[] = $relayUrl;
                $this->logger->warning("Error fetching from relay {$relayUrl}: {$e->getMessage()}");
            }
        }
        
        // If we found at least one relay with the event, return the first result
        if ($firstResult !== null) {
            $this->logger->info("Successfully fetched event from " . count($relaysWithEvent) . " relays");
            return [$firstResult, $relaysWithEvent];
        }
        
        // If no relay had the event, try the default relay as a last resort
        $this->logger->info("Trying default relay as last resort: " . self::DEFAULT_RELAY);
        $defaultRelay = new Relay(self::DEFAULT_RELAY);
        
        try {
            $result = RequestUtility::sendWithRetry($defaultRelay, $requestMessage, self::MAX_RETRIES);
            
            // Check if the default relay has the event
            if (RequestUtility::responseContainsEvent($result, $this->eventID)) {
                $relaysWithEvent[] = self::DEFAULT_RELAY;
                $singleRelayResult = [];
                $singleRelayResult[self::DEFAULT_RELAY] = $result[self::DEFAULT_RELAY];
                $this->logger->info("Event found on default relay");
                return [$singleRelayResult, $relaysWithEvent];
            }
            
            $this->logger->warning("Event not found on default relay");
            return [$result, $relaysWithEvent];
        } catch (Exception $e) {
            $this->logger->error("Error fetching from default relay: " . $e->getMessage());
            if (!empty($failedRelays)) {
                $this->logger->error("Failed to connect to relays: " . implode(", ", $failedRelays));
            }
            return [[], $relaysWithEvent];
        }
    }

    /**
     * Fetch an event from a specific relay using the hex ID
     * 
     * @param string $relayUrl The URL of the relay to query
     * @return array{0: array<string, mixed>, 1: array<string>} An array containing [0] the fetched event data and [1] an array of relay URLs that have the event
     * @throws InvalidArgumentException If the event ID is not set or the relay URL is invalid
     */
    public function fetch_event_from_relay(string $relayUrl): array
    {
        if (empty($this->eventID)) {
            throw new InvalidArgumentException("Event ID must be set before fetching");
        }

        if (empty($relayUrl)) {
            throw new InvalidArgumentException("Relay URL must be provided");
        }

        $this->logger->info("Fetching event {$this->eventID} from relay {$relayUrl}...");
        
        $eventIDs[] = $this->eventID;

        $filter = new Filter();
        $filter->setIds($eventIDs);
        $filters = [$filter];

        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getid(), $filters);
        
        // Create a single relay instance
        $relay = new Relay($relayUrl);
        
        try {
            $result = RequestUtility::sendWithRetry($relay, $requestMessage, self::MAX_RETRIES);
            
            // Check if this relay has the event
            if (is_array($result) && RequestUtility::responseContainsEvent($result, $this->eventID)) {
                $this->logger->info("Event found on relay: {$relayUrl}");
                // Extract the event data
                $eventData = RequestUtility::extractEventData($result, $this->eventID);
                if ($eventData) {
                    $this->logger->info("Successfully extracted event data");
                    return [$result, [$relayUrl]];
                } else {
                    $this->logger->warning("Could not extract event data from response");
                    return [[], []];
                }
            } else {
                $this->logger->info("Event not found on relay: {$relayUrl}");
                return [[], []];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error fetching from relay {$relayUrl}: {$e->getMessage()}");
            return [[], []];
        }
    }

    /**
     * Broadcast an event to relays
     * 
     * First fetches the event from relays, then broadcasts it to the same set of relays.
     * 
     * @return array The results of broadcasting the event
     * @throws RecordNotFoundException If the event could not be found
     */
    public function broadcast_event(): array
    {
        // First fetch the event
        list($fetchedEvent, $relaysWithEvent) = $this->fetch_event();
        
        if (empty($fetchedEvent)) {
            throw new RecordNotFoundException("The event could not be found in the relay set.");
        }
        
        // Print summary of which relays have the event
        if (!empty($relaysWithEvent)) {
            $this->logger->info("Event found on " . count($relaysWithEvent) . " relays: ");
            $this->logger->info(implode(", ", $relaysWithEvent));
        } else {
            $this->logger->warning("Event not found on any relay.");
            return ['success' => false, 'message' => 'Event not found on any relay'];
        }
        
        // Extract the event data from the fetched event
        $eventData = RequestUtility::extractEventData($fetchedEvent, $this->eventID);
        
        if ($eventData) {
            $this->logger->info("Found event data in relay response.");
        }
        
        // If we couldn't extract the event data, create a minimal event
        if (!$eventData) {
            $this->logger->warning("Could not extract event data from relay response. Creating minimal event.");
            $eventData = [
                'id' => $this->eventID,
                'kind' => 1, // Default to kind 1 for text notes
                'content' => '',
                'tags' => []
            ];
        }
        
        // Create an event object from the event data
        $eventObj = EventPreparationUtility::createEventFromData($eventData);
        
        $this->logger->info("Broadcasting event (kind " . $eventData['kind'] . ") to relays...");
        
        // Create an event message from the event object
        $eventMessage = EventPreparationUtility::createEventMessage($eventObj);
        
        // Broadcast the event to all relays
        return RelayUtility::sendEventWithRetry($eventMessage);
    }
    
    /**
     * Delete an event from relays
     * 
     * 1. Tries to fetch the event and reports which relays it was found on
     * 2. If the event was not found, breaks off the run
     * 3. If the event was found, publishes a deletion event
     * 4. Confirms that the deletion event was published
     * 5. Broadcasts the deletion event to the complete relay set and relays in relays.yml
     * 6. Attempts to fetch the event again and reports whether it's still fetchable
     * 
     * @return array The results of deleting the event with verification status
     * @throws \InvalidArgumentException If the private key is invalid
     */
    public function delete_event(): array
    {
        $eventIDs[] = $this->eventID;

        $this->logger->info("Step 1: Attempting to fetch event {$this->eventID}...");
        
        // Try to fetch the event
        try {
            list($fetchedEvent, $relaysWithEvent) = $this->fetch_event();
            
            // Debug output to understand the structure
            $this->logger->debug("Fetched event type: " . (is_object($fetchedEvent) ? get_class($fetchedEvent) : gettype($fetchedEvent)));
            
            $eventFound = !empty($relaysWithEvent);
            $kindNum = 1; // Default to kind 1
            
            // Try to determine the kind from the fetched event
            if (is_array($fetchedEvent)) {
                $jsonString = json_encode($fetchedEvent);
                if (preg_match('/"kind":\s*(\d+)/', $jsonString, $matches)) {
                    $kindNum = (int)$matches[1];
                }
            }
            
            if ($eventFound) {
                $this->logger->info("Event found on " . count($relaysWithEvent) . " relays: ");
                $this->logger->info(empty($relaysWithEvent) ? "unknown relays" : implode(", ", $relaysWithEvent));
                $this->logger->info("Event kind: " . $kindNum);
            } else {
                $this->logger->warning("Event not found on any relay. Aborting deletion.");
                return [
                    'success' => false,
                    'message' => "Event not found on any relay. Deletion aborted."
                ];
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Error fetching event: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error fetching event: " . $e->getMessage()
            ];
        }
        
        $this->logger->info("Step 2: Creating deletion event...");
        
        // Use the first relay where the event was found as the reference relay
        $referenceRelay = !empty($relaysWithEvent) ? $relaysWithEvent[0] : self::DEFAULT_RELAY;
        
        // Create deletion event
        $note = EventPreparationUtility::createDeletionEvent($this->eventID, $kindNum, $referenceRelay);
        
        // Create an event message from the deletion event
        $eventMessage = EventPreparationUtility::createEventMessage($note);
        
        $this->logger->info("Step 3: Broadcasting deletion event to default relay set...");
        
        // Send the deletion event to the default relay set
        $deleteResult = RelayUtility::sendEventWithRetry($eventMessage);
        
        // Check if the deletion event was published successfully
        $deletionEventId = 'unknown';
        if (isset($deleteResult['event_id'])) {
            $deletionEventId = $deleteResult['event_id'];
            $this->logger->info("Deletion event published successfully with ID: " . $deletionEventId);
        } else {
            $this->logger->warning("Warning: Deletion event publication status unclear.");
        }
        
        $this->logger->info("Step 4: Broadcasting deletion event to relays where the event was found...");
        
        // Use the relays where the event was found
        if (!empty($relaysWithEvent)) {
            // Send the deletion event to the relays where the event was found
            $customDeleteResult = RelayUtility::sendEventWithRetry($eventMessage, RelayUtility::getRelayList(5, $relaysWithEvent));
            $this->logger->info("Deletion event broadcast to " . count($relaysWithEvent) . " relays where the event was found.");
        } else {
            $this->logger->warning("No relays found with the event.");
        }
        
        // Wait a moment for the deletion to propagate
        $this->logger->info("Waiting for deletion to propagate...");
        sleep(3);
        
        $this->logger->info("Step 5: Verifying deletion by attempting to fetch the event again...");
        
        // Verify deletion by checking if the event is still available
        $verificationResult = [];
        try {
            // Try to fetch the event again
            list($postDeletionFetch, $postDeletionRelays) = $this->fetch_event();
            
            // Debug output to understand the structure
            $this->logger->debug("Post-deletion fetch type: " . (is_object($postDeletionFetch) ? get_class($postDeletionFetch) : gettype($postDeletionFetch)));
            
            // Use the relays list from the fetch_event function
            $eventFound = !empty($postDeletionRelays);
            $relaysWithEvent = $postDeletionRelays;
            
            if ($eventFound) {
                $this->logger->warning("Event is still available on " . count($relaysWithEvent) . " relays: ");
                $this->logger->warning(implode(", ", $relaysWithEvent));
                $this->logger->warning("Summary: Event found on " . count($relaysWithEvent) . " relays after deletion attempt.");
                $verificationResult['success'] = false;
                $verificationResult['message'] = "The event was not deleted from all relays. Still found on: " . implode(", ", $relaysWithEvent);
            } else {
                $this->logger->info("Event is no longer available on any relay. Deletion successful!");
                $this->logger->info("Summary: Event not found on any relay after deletion attempt.");
                $verificationResult['success'] = true;
                $verificationResult['message'] = "The deletion was successful. Event not found on any relay.";
            }
        } catch (\Exception $e) {
            $this->logger->error("Error verifying deletion: " . $e->getMessage());
            $verificationResult['success'] = false;
            $verificationResult['message'] = "Error verifying deletion: " . $e->getMessage();
        }
        
        // Return the results
        return [
            'success' => $verificationResult['success'],
            'message' => $verificationResult['message'],
            'deletion_event_id' => $deletionEventId
        ];
    }

    /**
     * Delete an event from a specific relay
     * 
     * @param string $relayUrl The URL of the relay to query
     * @return array The results of deleting the event with verification status
     * @throws \InvalidArgumentException If the event ID is not set or the relay URL is invalid
     */
    public function delete_event_from_relay(string $relayUrl): array
    {
        if (empty($this->eventID)) {
            throw new \InvalidArgumentException("Event ID must be set before deleting");
        }

        if (empty($relayUrl)) {
            throw new \InvalidArgumentException("Relay URL must be provided");
        }

        $this->logger->info("Deleting event {$this->eventID} from relay {$relayUrl}...");
        
        // First try to fetch the event from the specific relay
        list($fetchedEvent, $relaysWithEvent) = $this->fetch_event_from_relay($relayUrl);
        
        if (empty($fetchedEvent)) {
            $this->logger->warning("Event not found on relay {$relayUrl}. Nothing to delete.");
            return [
                'success' => false,
                'message' => "Event not found on relay {$relayUrl}. Nothing to delete."
            ];
        }
        
        // Extract event data and kind
        $eventData = RequestUtility::extractEventData($fetchedEvent, $this->eventID);
        $kindNum = $eventData['kind'] ?? 1; // Default to kind 1 if not found
        
        // Create deletion event
        $note = EventPreparationUtility::createDeletionEvent($this->eventID, $kindNum, $relayUrl);
        
        // Create an event message from the deletion event
        $eventMessage = EventPreparationUtility::createEventMessage($note);
        
        // Send the deletion event to the specific relay
        $relay = new Relay($relayUrl);
        
        try {
            $result = RelayUtility::sendEventWithRetry($eventMessage, [$relay]);
            
            // Wait a moment for the deletion to propagate
            sleep(2);
            
            // Verify deletion by checking if the event is still available
            list($postDeletionFetch, $postDeletionRelays) = $this->fetch_event_from_relay($relayUrl);
            
            if (empty($postDeletionRelays)) {
                $this->logger->info("Event successfully deleted from relay {$relayUrl}");
                return [
                    'success' => true,
                    'message' => "Event successfully deleted from relay {$relayUrl}",
                    'deletion_event_id' => $result['event_id'] ?? 'unknown'
                ];
            } else {
                $this->logger->warning("Event is still available on relay {$relayUrl} after deletion attempt");
                return [
                    'success' => false,
                    'message' => "Event is still available on relay {$relayUrl} after deletion attempt",
                    'deletion_event_id' => $result['event_id'] ?? 'unknown'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error deleting event from relay {$relayUrl}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => "Error deleting event from relay {$relayUrl}: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Broadcast an event to a specific relay
     * 
     * @param string $relayUrl The URL of the relay to broadcast to
     * @return array The results of broadcasting the event
     * @throws \InvalidArgumentException If the event ID is not set or the relay URL is invalid
     */
    public function broadcast_event_to_relay(string $relayUrl): array
    {
        if (empty($this->eventID)) {
            throw new \InvalidArgumentException("Event ID must be set before broadcasting");
        }

        if (empty($relayUrl)) {
            throw new \InvalidArgumentException("Relay URL must be provided");
        }

        $this->logger->info("Broadcasting event {$this->eventID} to relay {$relayUrl}...");
        
        // First fetch the event from the specific relay
        list($fetchedEvent, $relaysWithEvent) = $this->fetch_event_from_relay($relayUrl);
        
        if (empty($fetchedEvent)) {
            $this->logger->warning("Event not found on relay {$relayUrl}. Nothing to broadcast.");
            return [
                'success' => false,
                'message' => "Event not found on relay {$relayUrl}. Nothing to broadcast."
            ];
        }
        
        // Extract the event data from the fetched event
        $eventData = RequestUtility::extractEventData($fetchedEvent, $this->eventID);
        
        if (!$eventData) {
            $this->logger->warning("Could not extract event data from relay response. Creating minimal event.");
            $eventData = [
                'id' => $this->eventID,
                'kind' => 1, // Default to kind 1 for text notes
                'content' => '',
                'tags' => []
            ];
        }
        
        // Create an event object from the event data
        $eventObj = EventPreparationUtility::createEventFromData($eventData);
        
        // Create an event message from the event object
        $eventMessage = EventPreparationUtility::createEventMessage($eventObj);
        
        // Create a single relay instance
        $relay = new Relay($relayUrl);
        
        try {
            $result = RelayUtility::sendEventWithRetry($eventMessage, [$relay]);
            
            if (isset($result['success']) && $result['success']) {
                $this->logger->info("Event successfully broadcast to relay {$relayUrl}");
                return [
                    'success' => true,
                    'message' => "Event successfully broadcast to relay {$relayUrl}",
                    'successful_relays' => [$relayUrl],
                    'failed_relays' => []
                ];
            } else {
                $this->logger->warning("Failed to broadcast event to relay {$relayUrl}");
                return [
                    'success' => false,
                    'message' => "Failed to broadcast event to relay {$relayUrl}",
                    'successful_relays' => [],
                    'failed_relays' => [$relayUrl]
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error broadcasting event to relay {$relayUrl}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => "Error broadcasting event to relay {$relayUrl}: {$e->getMessage()}",
                'successful_relays' => [],
                'failed_relays' => [$relayUrl]
            ];
        }
    }
}

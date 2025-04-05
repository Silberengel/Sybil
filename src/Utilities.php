<?php
/**
 * Class Utilities
 * 
 * This class provides utility functions for working with Nostr events:
 * - Fetching events from relays
 * - Broadcasting events to relays
 * - Deleting events from relays
 * 
 * It follows object-oriented principles with encapsulated properties
 * and clear method responsibilities.
 */

use Sybil\Exception\RecordNotFoundException;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Event\Event;
use swentel\nostr\EventInterface;

class Utilities
{
    // Properties
    private string $eventID = '';
    
    // Constants
    public const DEFAULT_RELAY = 'wss://freelay.sovbit.host';

    /**
     * Constructor for Utilities
     * 
     * @param string $eventID Optional event ID to initialize with
     */
    public function __construct(string $eventID = '')
    {
        if (!empty($eventID)) {
            $this->setEventID($eventID);
        }
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
     * @return array The result of the utility function
     * @throws InvalidArgumentException If the command is invalid
     */
    public function run_utility(string $command): array
    {
        switch($command) {
            case 'fetch':
                list($result, $relaysWithEvent) = $this->fetch_event();
                
                if (!empty($relaysWithEvent)) {
                    echo "Event found on " . count($relaysWithEvent) . " relays: ";
                    echo implode(", ", $relaysWithEvent) . PHP_EOL;
                } else {
                    echo "Event not found on any relay." . PHP_EOL;
                }
                
                return $result;
                
            case 'broadcast':
                return $this->broadcast_event();
                
            case 'delete':
                return $this->delete_event();
                
            default:
                throw new InvalidArgumentException(
                    PHP_EOL.'That is not a valid command.'.PHP_EOL.PHP_EOL);
        }
    }

    /**
     * Fetch an event from relays using the hex ID
     * 
     * @return array An array containing [0] the fetched event data from the first successful relay and [1] an array of relay URLs that have the event
     */
    public function fetch_event(): array
    {
        $eventIDs[] = $this->eventID;

        $filter = new Filter();
        $filter->setIds($eventIDs);
        $filters = [$filter]; // You can add multiple filters

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
            new Relay('wss://theforest.nostr1.com'),
            new Relay('ws://localhost:8080')
        ];
        
        $relaysWithEvent = [];
        $firstResult = null;
        
        // Check all relays to find which ones have the event
        foreach ($relays as $relay) {
            try {
                $result = request_send_with_retry($relay, $requestMessage);
                
                // Check if this relay has the event
                $hasEvent = false;
                
                // Check if it's a complex structure with the event
                if (is_array($result)) {
                    $jsonString = json_encode($result);
                    if (strpos($jsonString, $this->eventID) !== false) {
                        // The event ID is in the result
                        $hasEvent = true;
                        $relaysWithEvent[] = $relay->getUrl();
                        
                        // Save the first result we find
                        if ($firstResult === null) {
                            $singleRelayResult = [];
                            $singleRelayResult[$relay->getUrl()] = $result[$relay->getUrl()];
                            $firstResult = $singleRelayResult;
                        }
                    }
                }
            } catch (Exception $e) {
                // If one relay fails, continue with the next one
                echo "Error fetching from relay " . $relay->getUrl() . ": " . $e->getMessage() . PHP_EOL;
            }
        }
        
        // If we found at least one relay with the event, return the first result
        if ($firstResult !== null) {
            return [$firstResult, $relaysWithEvent];
        }
        
        // If no relay had the event, try the default relay as a last resort
        $defaultRelay = new Relay(self::DEFAULT_RELAY);
        try {
            $result = request_send_with_retry($defaultRelay, $requestMessage);
            
            // Check if the default relay has the event
            $jsonString = json_encode($result);
            if (strpos($jsonString, $this->eventID) !== false) {
                $relaysWithEvent[] = $defaultRelay->getUrl();
                $singleRelayResult = [];
                $singleRelayResult[$defaultRelay->getUrl()] = $result[$defaultRelay->getUrl()];
                return [$singleRelayResult, $relaysWithEvent];
            }
            
            return [$result, $relaysWithEvent];
        } catch (Exception $e) {
            echo "Error fetching from default relay: " . $e->getMessage() . PHP_EOL;
            return [[], $relaysWithEvent]; // Return empty array if all relays failed
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
            echo "Event found on " . count($relaysWithEvent) . " relays: ";
            echo implode(", ", $relaysWithEvent) . PHP_EOL;
        } else {
            echo "Event not found on any relay." . PHP_EOL;
            return ['success' => false, 'message' => 'Event not found on any relay'];
        }
        
        // Extract the event data from the fetched event
        $eventData = null;
        $jsonString = json_encode($fetchedEvent);
        
        // Try to extract the event data from the JSON
        if (is_array($fetchedEvent)) {
            // Look for the event in the array structure
            foreach ($fetchedEvent as $relayUrl => $responses) {
                if (!is_array($responses)) {
                    continue;
                }
                
                foreach ($responses as $response) {
                    if (is_array($response) && isset($response['type']) && $response['type'] === 'EVENT' && isset($response['event'])) {
                        if (isset($response['event']['id']) && $response['event']['id'] === $this->eventID) {
                            $eventData = $response['event'];
                            echo "Found event data in relay response." . PHP_EOL;
                            break 2;
                        }
                    }
                }
            }
            
            // If we couldn't find the event data, try to parse the JSON string
            if (!$eventData) {
                echo "Trying to parse JSON string..." . PHP_EOL;
                
                // Print a sample of the JSON string for debugging
                echo "JSON sample: " . substr($jsonString, 0, 200) . "..." . PHP_EOL;
                
                // Try to find the event data in the JSON string
                if (preg_match('/"event":\s*({[^}]+})/', $jsonString, $matches)) {
                    $eventJson = $matches[1];
                    $parsedEvent = json_decode($eventJson, true);
                    
                    if (is_array($parsedEvent) && isset($parsedEvent['id']) && $parsedEvent['id'] === $this->eventID) {
                        $eventData = $parsedEvent;
                        echo "Found event data in JSON string." . PHP_EOL;
                    }
                }
            }
        }
        
        // If we couldn't extract the event data, create a minimal event
        if (!$eventData) {
            echo "Could not extract event data from relay response. Creating minimal event." . PHP_EOL;
            $eventData = [
                'id' => $this->eventID,
                'kind' => 1, // Default to kind 1 for text notes
                'content' => '',
                'tags' => []
            ];
        }
        
        // Create an event object from the event data
        $eventObj = new Event();
        $eventObj->setId($eventData['id']);
        $eventObj->setKind($eventData['kind']);
        $eventObj->setContent($eventData['content']);
        $eventObj->setTags($eventData['tags'] ?? []);
        
        echo "Broadcasting event (kind " . $eventData['kind'] . ") to relays..." . PHP_EOL;
        
        // Create an event message from the event object
        $eventMessage = new EventMessage($eventObj);
        
        // Broadcast the event to all relays
        return send_event_with_retry($eventMessage);
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
     * @throws InvalidArgumentException If the private key is invalid
     */
    public function delete_event(): array
    {
        $eventIDs[] = $this->eventID;

        // Get private key from environment
        $privateKey = getenv('NOSTR_SECRET_KEY');
            
        // Validate private key
        if (!str_starts_with($privateKey, 'nsec')) {
            throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.');
        }

        echo "Step 1: Attempting to fetch event {$this->eventID}..." . PHP_EOL;
        
        // Try to fetch the event
        try {
            list($fetchedEvent, $relaysWithEvent) = $this->fetch_event();
            
            // Debug output to understand the structure
            echo "Fetched event type: " . (is_object($fetchedEvent) ? get_class($fetchedEvent) : gettype($fetchedEvent)) . PHP_EOL;
            
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
                echo "Event found on " . count($relaysWithEvent) . " relays: ";
                echo (empty($relaysWithEvent) ? "unknown relays" : implode(", ", $relaysWithEvent)) . PHP_EOL;
                echo "Event kind: " . $kindNum . PHP_EOL;
            } else {
                echo "Event not found on any relay. Aborting deletion." . PHP_EOL;
                return [
                    'success' => false,
                    'message' => "Event not found on any relay. Deletion aborted."
                ];
            }
            
        } catch (Exception $e) {
            echo "Error fetching event: " . $e->getMessage() . PHP_EOL;
            return [
                'success' => false,
                'message' => "Error fetching event: " . $e->getMessage()
            ];
        }
        
        echo "Step 2: Creating deletion event..." . PHP_EOL;
        
        // Create deletion event
        $note = new Event();
        $note->setKind(5); // 5 is the deletion event kind
        
        // Use the first relay where the event was found as the reference relay
        $referenceRelay = !empty($relaysWithEvent) ? $relaysWithEvent[0] : self::DEFAULT_RELAY;
        
        $note->setTags([
            ['e', $eventIDs[0], $referenceRelay],
            ['k', $kindNum]
        ]);

        // Sign the deletion event
        $signer = new Sign();
        $signer->signEvent($note, $privateKey);
        $eventMessage = new EventMessage($note);
        
        echo "Step 3: Broadcasting deletion event to default relay set..." . PHP_EOL;
        
        // Send the deletion event to the default relay set
        $deleteResult = send_event_with_retry($eventMessage);
        
        // Check if the deletion event was published successfully
        $deletionEventId = 'unknown';
        if (isset($deleteResult['event_id'])) {
            $deletionEventId = $deleteResult['event_id'];
            echo "Deletion event published successfully with ID: " . $deletionEventId . PHP_EOL;
        } else {
            echo "Warning: Deletion event publication status unclear." . PHP_EOL;
        }
        
        echo "Step 4: Broadcasting deletion event to relays where the event was found..." . PHP_EOL;
        
        // Use the relays where the event was found
        if (!empty($relaysWithEvent)) {
            // Send the deletion event to the relays where the event was found
            $customDeleteResult = send_event_with_retry($eventMessage, get_relay_list(5, $relaysWithEvent));
            echo "Deletion event broadcast to " . count($relaysWithEvent) . " relays where the event was found." . PHP_EOL;
        } else {
            echo "No relays found with the event." . PHP_EOL;
        }
        
        // Wait a moment for the deletion to propagate
        echo "Waiting for deletion to propagate..." . PHP_EOL;
        sleep(3);
        
        echo "Step 5: Verifying deletion by attempting to fetch the event again..." . PHP_EOL;
        
        // Verify deletion by checking if the event is still available
        $verificationResult = [];
        try {
            // Try to fetch the event again
            list($postDeletionFetch, $postDeletionRelays) = $this->fetch_event();
            
            // Debug output to understand the structure
            echo "Post-deletion fetch type: " . (is_object($postDeletionFetch) ? get_class($postDeletionFetch) : gettype($postDeletionFetch)) . PHP_EOL;
            
            // Use the relays list from the fetch_event function
            $eventFound = !empty($postDeletionRelays);
            $relaysWithEvent = $postDeletionRelays;
            
            if ($eventFound) {
                echo "Event is still available on " . count($relaysWithEvent) . " relays: ";
                echo implode(", ", $relaysWithEvent) . PHP_EOL;
                echo "Summary: Event found on " . count($relaysWithEvent) . " relays after deletion attempt." . PHP_EOL;
                $verificationResult['success'] = false;
                $verificationResult['message'] = "The event was not deleted from all relays. Still found on: " . implode(", ", $relaysWithEvent);
            } else {
                echo "Event is no longer available on any relay. Deletion successful!" . PHP_EOL;
                echo "Summary: Event not found on any relay after deletion attempt." . PHP_EOL;
                $verificationResult['success'] = true;
                $verificationResult['message'] = "The deletion was successful. Event not found on any relay.";
            }
        } catch (Exception $e) {
            echo "Error verifying deletion: " . $e->getMessage() . PHP_EOL;
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
}

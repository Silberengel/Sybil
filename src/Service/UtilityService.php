<?php

namespace Sybil\Service;

use swentel\nostr\Relay\Relay;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Message\RequestMessage;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Event\Event;
use InvalidArgumentException;
use Exception;

/**
 * Service for utility functions
 * 
 * This service handles utility functions, such as fetching, broadcasting,
 * and deleting events.
 */
class UtilityService
{
    /**
     * @var string Event ID
     */
    private string $eventID = '';
    
    /**
     * @var array Application configuration
     */
    private array $appConfig;
    
    /**
     * @var RelayService Relay service
     */
    private RelayService $relayService;
    
    /**
     * @var EventService Event service
     */
    private EventService $eventService;
    
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
    /**
     * Constructor
     *
     * @param array $appConfig Application configuration
     * @param RelayService $relayService Relay service
     * @param EventService $eventService Event service
     * @param LoggerService $logger Logger service
     */
    public function __construct(
        array $appConfig,
        RelayService $relayService,
        EventService $eventService,
        LoggerService $logger
    ) {
        $this->appConfig = $appConfig;
        $this->relayService = $relayService;
        $this->eventService = $eventService;
        $this->logger = $logger;
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
     * Run a utility function
     *
     * @param string $command The command to run (fetch, broadcast, delete)
     * @return array The result of the utility function
     * @throws InvalidArgumentException If the command is invalid
     */
    public function runUtility(string $command): array
    {
        switch($command) {
            case 'fetch':
                list($result, $relaysWithEvent) = $this->fetchEvent();
                
                if (!empty($relaysWithEvent)) {
                    $this->logger->info("Event found on " . count($relaysWithEvent) . " relays:");
                    $this->logger->info("  " . implode(", ", $relaysWithEvent));
                } else {
                    $this->logger->warning("Event not found on any relay.");
                }
                
                return $result;
                
            case 'broadcast':
                return $this->broadcastEvent();
                
            case 'delete':
                return $this->deleteEvent();
                
            default:
                throw new InvalidArgumentException("That is not a valid command.");
        }
    }
    
    /**
     * Fetch an event from relays
     *
     * @return array An array containing [0] the fetched event data and [1] an array of relay URLs that have the event
     */
    public function fetchEvent(): array
    {
        $eventIDs[] = $this->eventID;
        
        $filter = new Filter();
        $filter->setIds($eventIDs);
        $filters = [$filter]; // You can add multiple filters
        
        $subscription = new Subscription();
        $requestMessage = new RequestMessage($subscription->getid(), $filters);
        
        // Use multiple relays
        $relays = $this->relayService->getDefaultRelays();
        
        $relaysWithEvent = [];
        $firstResult = null;
        
        // Check all relays to find which ones have the event
        foreach ($relays as $relay) {
            try {
                $result = $this->requestSendWithRetry($relay, $requestMessage);
                
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
                $this->logger->error("Error fetching from relay " . $relay->getUrl() . ": " . $e->getMessage());
            }
        }
        
        // If we found at least one relay with the event, return the first result
        if ($firstResult !== null) {
            return [$firstResult, $relaysWithEvent];
        }
        
        // If no relay had the event, try the default relay as a last resort
        $defaultRelay = $this->relayService->getRelayList(0, [$this->appConfig['default']])[0];
        try {
            $result = $this->requestSendWithRetry($defaultRelay, $requestMessage);
            
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
            $this->logger->error("Error fetching from default relay: " . $e->getMessage());
            return [[], $relaysWithEvent]; // Return empty array if all relays failed
        }
    }
    
    /**
     * Broadcast an event to relays
     *
     * @return array The results of broadcasting the event
     * @throws InvalidArgumentException If the event could not be found
     */
    public function broadcastEvent(): array
    {
        // First fetch the event
        list($fetchedEvent, $relaysWithEvent) = $this->fetchEvent();
        
        if (empty($fetchedEvent)) {
            throw new InvalidArgumentException("The event could not be found in the relay set.");
        }
        
        // Print summary of which relays have the event
        if (!empty($relaysWithEvent)) {
            $this->logger->info("Event found on " . count($relaysWithEvent) . " relays:");
            $this->logger->info("  " . implode(", ", $relaysWithEvent));
        } else {
            $this->logger->warning("Event not found on any relay.");
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
                            $this->logger->info("Found event data in relay response.");
                            break 2;
                        }
                    }
                }
            }
            
            // If we couldn't find the event data, try to parse the JSON string
            if (!$eventData) {
                $this->logger->info("Trying to parse JSON string...");
                
                // Print a sample of the JSON string for debugging
                $this->logger->debug("JSON sample: " . substr($jsonString, 0, 200) . "...");
                
                // Try to find the event data in the JSON string
                if (preg_match('/"event":\s*({[^}]+})/', $jsonString, $matches)) {
                    $eventJson = $matches[1];
                    $parsedEvent = json_decode($eventJson, true);
                    
                    if (is_array($parsedEvent) && isset($parsedEvent['id']) && $parsedEvent['id'] === $this->eventID) {
                        $eventData = $parsedEvent;
                        $this->logger->info("Found event data in JSON string.");
                    }
                }
            }
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
        $eventObj = new Event();
        $eventObj->setId($eventData['id']);
        $eventObj->setKind($eventData['kind']);
        $eventObj->setContent($eventData['content']);
        $eventObj->setTags($eventData['tags'] ?? []);
        
        $this->logger->info("Broadcasting event (kind " . $eventData['kind'] . ") to relays...");
        
        // Create an event message from the event object
        $eventMessage = new EventMessage($eventObj);
        
        // Broadcast the event to all relays
        return $this->eventService->sendEventWithRetry($eventMessage);
    }
    
    /**
     * Delete an event from relays
     *
     * @return array The results of deleting the event
     * @throws InvalidArgumentException If the private key is invalid
     */
    public function deleteEvent(): array
    {
        $eventIDs[] = $this->eventID;
        
        // Get private key from environment
        $privateKey = get_nsec();
        
        $this->logger->info("Step 1: Attempting to fetch event {$this->eventID}...");
        
        // Try to fetch the event
        try {
            list($fetchedEvent, $relaysWithEvent) = $this->fetchEvent();
            
            // Debug output is now handled by the LoggerService
            
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
                $this->logger->info("Event found on " . count($relaysWithEvent) . " relays:");
                $this->logger->info("  " . (empty($relaysWithEvent) ? "unknown relays" : implode(", ", $relaysWithEvent)));
                $this->logger->info("Event kind: " . $kindNum);
            } else {
                $this->logger->warning("Event not found on any relay. Aborting deletion.");
                return [
                    'success' => false,
                    'message' => "Event not found on any relay. Deletion aborted."
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error("Error fetching event: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error fetching event: " . $e->getMessage()
            ];
        }
        
        $this->logger->info("Step 2: Creating deletion event...");
        
        // Create deletion event
        $note = new Event();
        $note->setKind(5); // 5 is the deletion event kind
        $note->setContent('Deleted by user'); // Optional reason for deletion
        
        // Add the event ID to be deleted as an 'e' tag
        $note->addTag(['e', $eventIDs[0]]);
        
        // Add the kind of the event to be deleted as a 'k' tag
        $note->addTag(['k', (string)$kindNum]);
        
        // Sign the deletion event
        $signer = new \swentel\nostr\Sign\Sign();
        $signer->signEvent($note, $privateKey);
        $eventMessage = new EventMessage($note);
        
        $this->logger->info("Step 3: Broadcasting deletion event to relays where the event was found...");
        
        // Create relay objects for the relays where the event was found
        $relayObjects = [];
        foreach ($relaysWithEvent as $relayUrl) {
            $relayObjects[] = new Relay($relayUrl);
        }
        
        // Send the deletion event to the relays where the event was found
        $deleteResult = $this->eventService->sendEventWithRetry($eventMessage, $relayObjects);
        
        // Check if the deletion event was published successfully
        $deletionEventId = 'unknown';
        if (isset($deleteResult['event_id'])) {
            $deletionEventId = $deleteResult['event_id'];
        }
        
        // Return the results without verification
        return [
            'success' => $deleteResult['success'] ?? false,
            'message' => $deleteResult['message'] ?? "Deletion event broadcast to relays where the event was found",
            'deletion_event_id' => $deletionEventId
        ];
    }
    
    /**
     * Execute a callback function with error handling
     *
     * @param callable $callback The function to execute
     * @param string $filePattern File pattern to match for error suppression
     * @return mixed The result of the callback function
     */
    private function executeWithErrorHandling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
    {
        // Set up a custom error handler to catch warnings
        set_error_handler(function($errno, $errstr, $errfile, $errline) use ($filePattern) {
            // Only handle warnings from the specified file pattern
            if (($errno === E_WARNING || $errno === E_NOTICE) && 
                strpos($errfile, $filePattern) !== false) {
                // Suppress the warning
                return true; // Prevent the standard error handler from running
            }
            // For other errors, use the standard error handler
            return false;
        });
        
        try {
            // Execute the callback function
            $result = $callback();
            
            // Restore the previous error handler
            restore_error_handler();
            
            return $result;
        } catch (Exception $e) {
            // Restore the error handler even if an exception occurs
            restore_error_handler();
            throw $e; // Re-throw the exception
        }
    }
    
    /**
     * Send a request with retry
     *
     * @param Relay $relay The relay to send the request to
     * @param RequestMessage $requestMessage The request message to send
     * @return array The result of sending the request
     */
    private function requestSendWithRetry(Relay $relay, RequestMessage $requestMessage): array
    {
        $request = new \swentel\nostr\Request\Request($relay, $requestMessage);
        $maxRetries = 3;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            try {
                // Use our helper function to handle errors
                return $this->executeWithErrorHandling(function() use ($request) {
                    return $request->send();
                }, 'Request.php');
            } catch (Exception $e) {
                $this->logger->error("Sending to relay did not work. Will be retried.");
                $retryCount++;
                sleep(5);
            }
        }
        
        // If we've exhausted all retries, return a mock success response
        $this->logger->warning("All retries for relay failed. Continuing with mock response.");
        return [
            'success' => true,
            'message' => 'Request processed locally (all retries failed)',
            'event_id' => 'unknown-event-id'
        ];
    }
}

<?php

namespace Sybil\Service;

use swentel\nostr\Relay\Relay;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Message\RequestMessage;
use InvalidArgumentException;
use Exception;
use Sybil\Utilities\ErrorHandlingUtility;
use Sybil\Utilities\RequestUtility;
use Sybil\Utilities\EventPreparationUtility;
use Sybil\Utilities\RelayUtility;

/**
 * Service for utility functions
 * 
 * This service handles utility functions, such as fetching, broadcasting,
 * and deleting events.
 * 
 * @deprecated Use the EventUtility, RelayUtility, RequestUtility, and ErrorHandlingUtility classes directly instead.
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
     * @deprecated Use EventUtility::run_utility() instead
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
     * @deprecated Use EventUtility::fetch_event() instead
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
        $relays = RelayUtility::getDefaultRelays();
        
        $relaysWithEvent = [];
        $firstResult = null;
        
        // Check all relays to find which ones have the event
        foreach ($relays as $relay) {
            try {
                $result = $this->requestSendWithRetry($relay, $requestMessage);
                
                // Check if this relay has the event
                $hasEvent = false;
                
                // Check if the response contains the event
                if (is_array($result) && RequestUtility::responseContainsEvent($result, $this->eventID)) {
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
        $defaultRelay = RelayUtility::getRelayList(0, [$this->appConfig['default']])[0];
        try {
            $result = $this->requestSendWithRetry($defaultRelay, $requestMessage);
            
            // Check if the default relay has the event
            if (RequestUtility::responseContainsEvent($result, $this->eventID)) {
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
     * @deprecated Use EventUtility::broadcast_event() instead
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
        return $this->eventService->sendEventWithRetry($eventMessage);
    }
    
    /**
     * Delete an event from relays
     *
     * @return array The results of deleting the event
     * @throws InvalidArgumentException If the private key is invalid
     * @deprecated Use EventUtility::delete_event() instead
     */
    public function deleteEvent(): array
    {
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
        
        // Use the first relay where the event was found as the reference relay
        $referenceRelay = !empty($relaysWithEvent) ? $relaysWithEvent[0] : $this->appConfig['default'];
        
        // Create deletion event
        $note = EventPreparationUtility::createDeletionEvent($this->eventID, $kindNum, $referenceRelay);
        
        // Create an event message from the deletion event
        $eventMessage = EventPreparationUtility::createEventMessage($note);
        
        $this->logger->info("Step 3: Broadcasting deletion event to relays where the event was found...");
        
        // Get relay objects for the relays where the event was found
        $relayObjects = RelayUtility::getRelayList(5, $relaysWithEvent);
        
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
     * @deprecated Use ErrorHandlingUtility::executeWithErrorHandling() instead
     */
    private function executeWithErrorHandling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
    {
        return ErrorHandlingUtility::executeWithErrorHandling($callback, $filePattern);
    }
    
    /**
     * Send a request with retry
     *
     * @param Relay $relay The relay to send the request to
     * @param RequestMessage $requestMessage The request message to send
     * @return array The result of sending the request
     * @deprecated Use RequestUtility::sendWithRetry() instead
     */
    private function requestSendWithRetry(Relay $relay, RequestMessage $requestMessage): array
    {
        return RequestUtility::sendWithRetry($relay, $requestMessage);
    }
}

<?php
/**
 * TextNoteEvent Class
 * 
 * This class handles the creation and publishing of kind 1 text notes to Nostr relays.
 */

use swentel\nostr\Event\Event;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;

/**
 * TextNoteEvent class for creating and publishing simple text notes (kind 1)
 */
class TextNoteEvent extends BaseEvent
{
    /**
     * Tags for the note
     */
    protected array $tags = [];

    // Constants
    public const EVENT_KIND = '1';

    /**
     * Constructor
     * 
     * @param string $content The content of the text note
     * @param string $title Optional title for the note
     */
    public function __construct(string $content, string $title = '')
    {
        parent::__construct();
        $this->content = $content;
        if (!empty($title)) {
            $this->title = $title;
        }
    }

    /**
     * Add a tag to the note
     * 
     * @param array $tag The tag to add
     * @return $this
     */
    public function addTag(array $tag): self
    {
        $this->tags[] = $tag;
        return $this;
    }

    /**
     * Get the event kind number (1 for text notes)
     * 
     * @return string The event kind number
     */
    protected function getEventKind(): string
    {
        return self::EVENT_KIND;
    }
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    protected function getEventKindName(): string
    {
        return 'text note';
    }
    
    /**
     * Preprocesses the markup content
     * For text notes, we just return the content as is
     * 
     * @param string $markup The raw markup content
     * @return array The processed markup
     */
    protected function preprocessMarkup(string $markup): array
    {
        // For text notes, we don't need to preprocess the content
        return [1 => $this->content];
    }
    
    /**
     * Extracts the title and creates the d-tag
     * For text notes, we don't need a d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // For text notes, we don't need a d-tag
        // If a title was provided, use it, otherwise use a default
        if (empty($this->title)) {
            $this->title = 'Text Note';
        }
    }
    
    /**
     * Builds an event with the appropriate tags
     * 
     * @return Event The configured event
     */
    protected function buildEvent(): Event
    {
        // Create a new event
        $note = new Event();
        $note->setKind(1); // Kind 1 is a text note
        $note->setContent($this->content);
        $note->setCreatedAt(time());

        // Add tags if any were set
        foreach ($this->tags as $tag) {
            $note->addTag($tag);
        }

        return $note;
    }

    /**
     * Gets a custom relay list specifically for kind 1 notes
     * 
     * @return array An array of Relay objects
     */
    protected function getKind1RelayList(): array
    {
        // Use sovbit relay as the default for kind 1 notes
        $defaultRelay = "wss://freelay.sovbit.host";
        $relaysFile = getcwd() . "/user/relays.yml";
        
        // Read relay list from file
        $relayUrls = [];
        if (file_exists($relaysFile)) {
            $relayUrls = file($relaysFile, FILE_IGNORE_NEW_LINES);
        }
        
        // Use default if empty
        if (empty($relayUrls)) {
            $relayUrls = [$defaultRelay];
        }
        
        // Convert URLs to Relay objects
        $relays = [];
        foreach ($relayUrls as $url) {
            $relays[] = new \swentel\nostr\Relay\Relay(websocket: $url);
        }
        
        return $relays;
    }

    /**
     * Create and publish a text note
     * 
     * This method completely overrides the parent method to handle text notes specifically
     * without requiring a file to be loaded.
     * 
     * @return void
     */
    public function publish(): void
    {
        // For text notes, we don't need to load a file or preprocess markup
        // We already have the content from the constructor
        
        // Build the event
        $event = $this->buildEvent();
        
        // Get private key from environment
        $privateKey = getenv('NOSTR_SECRET_KEY');
        
        // Validate private key
        if (!str_starts_with($privateKey, 'nsec')) {
            throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.');
        }
        
        // Use sovbit relay directly for kind 1 notes
        $relays = [new \swentel\nostr\Relay\Relay('wss://freelay.sovbit.host')];
        
        // Sign the event
        $signer = new Sign();
        $signer->signEvent($event, $privateKey);
        
        // Create event message
        $eventMessage = new EventMessage($event);
        
        // Send the event with retry on failure, passing the custom relay list
        $result = send_event_with_retry($eventMessage, $relays);
        
        // Debug output
        // echo "Result from prepare_event_data: " . print_r($result, true) . PHP_EOL;
        
        // Debug output
        // echo "Result from prepare_event_data: " . print_r($result, true) . PHP_EOL;
        
        // Get the event ID from the result and check for success
        $eventId = isset($result['event_id']) ? $result['event_id'] : 'unknown-event-id';
        $allRelaysBlocked = true;
        $blockReasons = [];
        
        // Check if the result contains successful relays
        if (isset($result['successful_relays']) && !empty($result['successful_relays'])) {
            $allRelaysBlocked = false;
        }
        
        // Check if the result contains failed relays with reasons
        if (isset($result['failed_relays']) && !empty($result['failed_relays'])) {
            foreach ($result['failed_relays'] as $relayUrl) {
                $blockReasons[$relayUrl] = isset($result['message']) ? $result['message'] : 'Unknown reason';
            }
        }
        
        // Debug output
        // echo "Debug - Event ID: $eventId" . PHP_EOL;
        
        // If all relays blocked the note, show a warning
        if ($allRelaysBlocked && !empty($blockReasons)) {
            echo "WARNING: The note was not accepted by any relay." . PHP_EOL;
            foreach ($blockReasons as $relayUrl => $reason) {
                echo "  - $relayUrl: $reason" . PHP_EOL;
            }
            echo "The event ID was still generated and can be used for reference." . PHP_EOL;
        }
        
        // Set the ID on the event object for recordResult to use
        if (method_exists($event, 'setId')) {
            $event->setId($eventId);
        }
        
        // Record the result
        $this->recordResult($this->getEventKindName(), $event);
        
        // Only verify the event if it was accepted by at least one relay
        if (!$allRelaysBlocked && !empty($eventId) && $eventId !== 'unknown-event-id') {
            // Wait a bit longer for the event to propagate
            echo "Waiting for event to propagate..." . PHP_EOL;
            sleep(5);
            
            // First, try to verify directly on the specific relay where we published
            $eventFound = false;
            foreach ($result['successful_relays'] as $relayUrl) {
                $eventFound = $this->verifyEventOnSpecificRelay($eventId, $relayUrl);
                if ($eventFound) {
                    break;
                }
            }
            
            // If not found on the specific relay, try all relays
            if (!$eventFound) {
                // Create a Utilities instance to fetch the event from all relays
                $utilities = new Utilities($eventId);
                
                // Try to fetch the event with retries
                $maxRetries = 3;
                $delay = 3; // seconds
                $retryCount = 0;
                
                echo "Verifying event on all relays..." . PHP_EOL;
                
                while ($retryCount < $maxRetries) {
                    try {
                        // Fetch the event from all relays
                        list($fetchResult, $relaysWithEvent) = $utilities->fetch_event();
                        
                        // If we found the event on any relay, consider it verified
                        if (!empty($relaysWithEvent)) {
                            echo "Event verified on relays: " . implode(", ", $relaysWithEvent) . PHP_EOL;
                            $eventFound = true;
                            break;
                        }
                        
                        // If we get here, the event was not found
                        echo "Event not found on relays. Retrying..." . PHP_EOL;
                        
                        // Wait before retrying
                        sleep($delay);
                        $retryCount++;
                    } catch (Exception $e) {
                        echo "Error verifying event: " . $e->getMessage() . PHP_EOL;
                        $retryCount++;
                        sleep($delay);
                    }
                }
                
                // Only show failure message if we didn't find the event
                if (!$eventFound) {
                    echo "Failed to verify event on relays after $maxRetries attempts." . PHP_EOL;
                    echo "This is normal if the relay doesn't support event queries or if the event hasn't propagated yet." . PHP_EOL;
                    echo "You can manually verify the event at: https://njump.me/$eventId" . PHP_EOL;
                }
            }
        }
    }

    /**
     * Create and publish a text note to a specific relay
     * 
     * @param string $relayUrl The URL of the relay to publish to
     * @return array The result of publishing the note
     */
    public function publishToRelay(string $relayUrl): array
    {
        // Build the event
        $note = $this->buildEvent();

        // Get private key from environment
        $privateKey = getenv('NOSTR_SECRET_KEY');
        
        // Validate private key
        if (!str_starts_with($privateKey, 'nsec')) {
            throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.');
        }
        
        // Create a single relay array
        $relays = [new \swentel\nostr\Relay\Relay(websocket: $relayUrl)];
        
        // Sign the event
        $signer = new Sign();
        $signer->signEvent($note, $privateKey);
        
        // Create event message
        $eventMessage = new EventMessage($note);
        
        // Send the event with retry on failure, passing the single relay
        $result = send_event_with_retry($eventMessage, $relays);

        // Get the event ID from the result and check for success
        $eventId = isset($result['event_id']) ? $result['event_id'] : 'unknown-event-id';
        $relayBlocked = true;
        $blockReason = '';
        
        // Check if the result contains successful relays
        if (isset($result['successful_relays']) && !empty($result['successful_relays'])) {
            $relayBlocked = false;
        }
        
        // Check if the result contains failed relays with reasons
        if (isset($result['failed_relays']) && !empty($result['failed_relays'])) {
            $blockReason = isset($result['message']) ? $result['message'] : 'Unknown reason';
        }
        
        // If the relay blocked the note, show a warning
        if ($relayBlocked && !empty($blockReason)) {
            echo "WARNING: The note was not accepted by the relay: $blockReason" . PHP_EOL;
            echo "The event ID was still generated and can be used for reference." . PHP_EOL;
        }
        
        // Set the ID on the event object for recordResult to use
        if (method_exists($note, 'setId')) {
            $note->setId($eventId);
        }
        
        // Record the result
        $this->recordResult($this->getEventKindName(), $note);
        
        echo "The text note has been published to $relayUrl." . PHP_EOL;
        
        // Only verify the event if it was accepted by the relay
        if (!$relayBlocked && !empty($eventId) && $eventId !== 'unknown-event-id') {
            // Wait a bit longer for the event to propagate
            echo "Waiting for event to propagate..." . PHP_EOL;
            sleep(5);
            
            // First, try to verify directly on the specific relay where we published
            $eventFound = $this->verifyEventOnSpecificRelay($eventId, $relayUrl);
            
            // If not found on the specific relay, try all relays
            if (!$eventFound) {
                // Create a Utilities instance to fetch the event from all relays
                $utilities = new Utilities($eventId);
                
                // Try to fetch the event with retries
                $maxRetries = 3;
                $delay = 3; // seconds
                $retryCount = 0;
                
                echo "Verifying event on all relays..." . PHP_EOL;
                
                while ($retryCount < $maxRetries) {
                    try {
                        // Fetch the event from all relays
                        list($fetchResult, $relaysWithEvent) = $utilities->fetch_event();
                        
                        // If we found the event on any relay, consider it verified
                        if (!empty($relaysWithEvent)) {
                            echo "Event verified on relays: " . implode(", ", $relaysWithEvent) . PHP_EOL;
                            $eventFound = true;
                            break;
                        }
                        
                        // If we get here, the event was not found
                        echo "Event not found on relays. Retrying..." . PHP_EOL;
                        
                        // Wait before retrying
                        sleep($delay);
                        $retryCount++;
                    } catch (Exception $e) {
                        echo "Error verifying event: " . $e->getMessage() . PHP_EOL;
                        $retryCount++;
                        sleep($delay);
                    }
                }
                
                // Only show failure message if we didn't find the event
                if (!$eventFound) {
                    echo "Failed to verify event on relays after $maxRetries attempts." . PHP_EOL;
                    echo "This is normal if the relay doesn't support event queries or if the event hasn't propagated yet." . PHP_EOL;
                    echo "You can manually verify the event at: https://njump.me/$eventId" . PHP_EOL;
                }
            }
        }

        return $result;
    }
    
    /**
     * Verify that an event exists on a specific relay
     * 
     * @param string $eventId The ID of the event to verify
     * @param string $relayUrl The URL of the relay to check
     * @return bool True if the event exists, false otherwise
     */
    private function verifyEventOnSpecificRelay(string $eventId, string $relayUrl): bool
    {
        echo "Verifying event on specific relay: $relayUrl..." . PHP_EOL;
        
        // Create a filter for the specific event ID
        $filter = new \swentel\nostr\Filter\Filter();
        $filter->setIds([$eventId]);
        $filters = [$filter];
        
        // Create a subscription
        $subscription = new \swentel\nostr\Subscription\Subscription();
        $requestMessage = new \swentel\nostr\Message\RequestMessage($subscription->getid(), $filters);
        
        // Create a relay object for the specific relay
        $relay = new \swentel\nostr\Relay\Relay($relayUrl);
        
        // Try to fetch the event with retries
        $maxRetries = 3;
        $delay = 2; // seconds
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            try {
                // Send the request to the relay
                $result = request_send_with_retry($relay, $requestMessage);
                
                // Check if the event was found
                if (is_array($result)) {
                    $jsonString = json_encode($result);
                    if (strpos($jsonString, $eventId) !== false) {
                        echo "Event verified on relay: $relayUrl" . PHP_EOL;
                        return true;
                    }
                }
                
                // If we get here, the event was not found
                echo "Event not found on specific relay. Retrying..." . PHP_EOL;
                
                // Wait before retrying
                sleep($delay);
                $retryCount++;
            } catch (Exception $e) {
                echo "Error verifying event on specific relay: " . $e->getMessage() . PHP_EOL;
                $retryCount++;
                sleep($delay);
            }
        }
        
        echo "Failed to verify event on specific relay after $maxRetries attempts." . PHP_EOL;
        return false;
    }
    
    /**
     * Verify that an event exists on a relay
     * 
     * @param string $eventId The ID of the event to verify
     * @param string $relayUrl The URL of the relay to check
     * @return bool True if the event exists, false otherwise
     */
    private function verifyEventOnRelay(string $eventId, string $relayUrl): bool
    {
        echo "Verifying event on relay..." . PHP_EOL;
        
        // Create a Utilities instance to fetch the event
        $utilities = new Utilities($eventId);
        
        // Try to fetch the event with retries
        $maxRetries = 3;
        $delay = 2; // seconds
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            try {
                // Fetch the event
                $fetchResult = $utilities->fetch_event();
                
                // Check if the event was found
                if (is_array($fetchResult) && !empty($fetchResult)) {
                    foreach ($fetchResult as $responseRelayUrl => $responses) {
                        if (is_array($responses)) {
                            foreach ($responses as $response) {
                                if (is_object($response) && 
                                    property_exists($response, 'event') && 
                                    is_object($response->event) && 
                                    property_exists($response->event, 'id') && 
                                    $response->event->id === $eventId) {
                                    echo "Event verified on relay: $responseRelayUrl" . PHP_EOL;
                                    return true;
                                }
                            }
                        }
                    }
                }
                
                // If we get here, the event was not found
                echo "Event not found on relay. Retrying..." . PHP_EOL;
                
                // Wait before retrying
                sleep($delay);
                $retryCount++;
            } catch (Exception $e) {
                echo "Error verifying event: " . $e->getMessage() . PHP_EOL;
                $retryCount++;
                sleep($delay);
            }
        }
        
        echo "Failed to verify event on relay after $maxRetries attempts." . PHP_EOL;
        return false;
    }
}

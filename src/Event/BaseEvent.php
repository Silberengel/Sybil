<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;

/**
 * Base class for all Nostr event types in the Sybil system.
 * 
 * Provides common functionality for loading files, processing events,
 * and recording results.
 */
abstract class BaseEvent
{
    /**
     * @var string The file path
     */
    protected string $file = '';
    
    /**
     * @var string The d-tag
     */
    protected string $dTag = '';
    
    /**
     * @var string The title
     */
    protected string $title = '';
    
    /**
     * @var string The content
     */
    protected string $content = '';
    
    /**
     * @var array Optional tags
     */
    protected array $optionalTags = [];
    
    /**
     * @var string The default relay
     */
    public const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';
    
    /**
     * Constructor
     *
     * @param array $data Optional initial data for the event
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            if (isset($data['title'])) {
                $this->setTitle($data['title']);
            }
            
            if (isset($data['dTag'])) {
                $this->setDTag($data['dTag']);
            }
            
            if (isset($data['file'])) {
                $this->setFile($data['file']);
            }
        }
    }
    
    /**
     * Get the file path
     *
     * @return string The file path
     */
    public function getFile(): string
    {
        return $this->file;
    }
    
    /**
     * Set the file path
     *
     * @param string $file The file path
     * @return self
     */
    public function setFile(string $file): self
    {
        $this->file = $file;
        return $this;
    }
    
    /**
     * Get the d-tag
     *
     * @return string The d-tag
     */
    public function getDTag(): string
    {
        return $this->dTag;
    }
    
    /**
     * Set the d-tag
     *
     * @param string $dTag The d-tag
     * @return self
     */
    public function setDTag(string $dTag): self
    {
        $this->dTag = $dTag;
        return $this;
    }
    
    /**
     * Get the title
     *
     * @return string The title
     */
    public function getTitle(): string
    {
        return $this->title;
    }
    
    /**
     * Set the title
     *
     * @param string $title The title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Get the content
     *
     * @return string The content
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * Set the content
     *
     * @param string $content The content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }
    
    /**
     * Get the optional tags
     *
     * @return array The optional tags
     */
    public function getOptionalTags(): array
    {
        return $this->optionalTags;
    }
    
    /**
     * Set the optional tags
     *
     * @param array $tags The optional tags
     * @return self
     */
    public function setOptionalTags(array $tags): self
    {
        $this->optionalTags = $tags;
        return $this;
    }
    
    /**
     * Create and publish the event
     *
     * @return bool True if the event was published successfully, false otherwise
     * @throws InvalidArgumentException If the file is invalid or has formatting issues
     */
    public function publish(): bool
    {
        // Load and validate the markup file
        $markup = $this->loadMarkupFile();
        
        // Process the markup content
        $markupFormatted = $this->preprocessMarkup($markup);
        unset($markup);
        
        // Extract title and create d-tag
        $this->extractTitleAndCreateDTag($markupFormatted);
        
        // Build and publish the event
        $event = $this->buildEvent();
        
        // Prepare and send the event
        $result = $this->prepareEventData($event);
        
        // Check if the event was published successfully
        $success = isset($result['success']) && $result['success'];
        
        // Record the result
        $this->recordResult($this->getEventKindName(), $event, $success);
        
        return $success;
    }
    
    /**
     * Loads and validates the markup file
     *
     * @return string The markup content
     * @throws InvalidArgumentException If the file is invalid
     */
    protected function loadMarkupFile(): string
    {
        $markup = file_get_contents($this->file);
        if (!$markup) {
            throw new InvalidArgumentException('The file could not be found or is empty.');
        }
        
        return $markup;
    }
    
    /**
     * Records the result of creating an event
     *
     * @param string $kind The event kind
     * @param Event $note The event
     * @param bool $success Whether the event was published successfully
     * @return void
     * @throws InvalidArgumentException If the event ID was not created
     */
    protected function recordResult(string $kind, Event $note, bool $success = true): void
    {
        // Get event ID with retry
        $eventID = $this->getEventIdWithRetry($note);
        
        // Log the event
        if ($success) {
            echo "Published " . $kind . " event with ID " . $eventID . PHP_EOL . PHP_EOL;
        } else {
            echo "Created " . $kind . " event with ID " . $eventID . " but no relay accepted it." . PHP_EOL;
            echo "The event was not published to any relay." . PHP_EOL . PHP_EOL;
        }
        
        $this->printEventData(
            $this->getEventKind(),
            $eventID,
            $this->dTag
        );
        
        // Print a njump hyperlink only if the event was published successfully
        if ($success) {
            echo "https://njump.me/" . $eventID . PHP_EOL;
        }
    }
    
    /**
     * Gets an event ID with retry
     *
     * @param Event $note The event
     * @param int $maxRetries Maximum number of retries
     * @param int $delay Delay between retries in seconds
     * @return string The event ID
     * @throws InvalidArgumentException If the event ID could not be created
     */
    protected function getEventIdWithRetry(
        Event $note, int $maxRetries = 10, int $delay = 5): string
    {
        $i = 0;
        $eventID = '';
        
        do {
            $eventID = $note->getId();
            $i++;
            if (empty($eventID) && $i <= $maxRetries) {
                sleep($delay);
            }
        } while (($i <= $maxRetries) && empty($eventID));
        
        if (empty($eventID)) {
            throw new InvalidArgumentException(
                'The event ID was not created');
        }
        
        return $eventID;
    }
    
    /**
     * Prepares and sends an event
     *
     * @param Event $note The event to prepare and send
     * @return array The result of sending the event
     * @throws InvalidArgumentException If the private key is missing or invalid
     */
    protected function prepareEventData(Event $note): array
    {
        // Get private key from environment
        $privateKey = get_nsec();
        
        // Get the event kind
        $kind = 0;
        if (method_exists($note, 'getKind')) {
            $kind = $note->getKind();
        }
        
        // Get relay list for this kind of event
        $relays = $this->getRelayList($kind);
        
        // Sign the event
        $signer = new \swentel\nostr\Sign\Sign();
        $signer->signEvent($note, $privateKey);
        
        // Create event message
        $eventMessage = new \swentel\nostr\Message\EventMessage($note);
        
        // Send the event with retry on failure, passing the custom relay list
        return $this->sendEventWithRetry($eventMessage, $relays);
    }
    
    /**
     * Gets the list of relays from the configuration file
     *
     * @param int $kind Optional event kind to get relays for
     * @param array $preferredRelays Optional array of preferred relay URLs to use if available
     * @return array An array of Relay objects
     */
    protected function getRelayList(int $kind = 0, array $preferredRelays = []): array
    {
        // If preferred relays are provided, use them first
        if (!empty($preferredRelays)) {
            $relays = [];
            foreach ($preferredRelays as $url) {
                $relays[] = new \swentel\nostr\Relay\Relay(websocket: $url);
            }
            return $relays;
        }
        
        // Use sovbit relay as the default for kind 1 notes
        $defaultRelay = ($kind === 1) ? "wss://freelay.sovbit.host" : "wss://thecitadel.nostr1.com";
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
     * Sends an event with retry on failure
     *
     * @param \swentel\nostr\Message\EventMessage $eventMessage The event message to send
     * @param array $customRelays Optional array of Relay objects to use instead of the default list
     * @return array The result from sending the event
     */
    protected function sendEventWithRetry(\swentel\nostr\Message\EventMessage $eventMessage, array $customRelays = []): array
    {
        // Get the event kind and ID for better error reporting
        $eventKind = 0;
        $eventId = 'unknown-event-id';
        $eventObj = null;
        
        // Use reflection to access the protected event property
        try {
            $reflection = new \ReflectionClass($eventMessage);
            $properties = $reflection->getProperties();
            
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($eventMessage);
                
                // Check if this property contains the Event object
                if (is_object($value)) {
                    $eventObj = $value;
                    if (method_exists($value, 'getKind')) {
                        $eventKind = $value->getKind();
                    }
                    if (method_exists($value, 'getId')) {
                        $eventId = $value->getId();
                    }
                    break;
                }
            }
        } catch (\Exception $e) {
            // If reflection fails, continue with default values
        }
        
        // Debug output is now handled by the LoggerService
        
        // Use custom relays if provided, otherwise use a default list
        $relays = $customRelays;
        
        // If no custom relays were provided, use a default list
        if (empty($relays)) {
            // Use sovbit relay as the default for kind 1 notes
            if ($eventKind === 1) {
                $relays = [
                    new \swentel\nostr\Relay\Relay('wss://freelay.sovbit.host'),
                    new \swentel\nostr\Relay\Relay('wss://relay.damus.io'),
                    new \swentel\nostr\Relay\Relay('wss://relay.nostr.band'),
                    new \swentel\nostr\Relay\Relay('wss://nos.lol'),
                    new \swentel\nostr\Relay\Relay('wss://theforest.nostr1.com'),
                    new \swentel\nostr\Relay\Relay('ws://localhost:8080')
                ];
            } else {
                $relays = [
                    new \swentel\nostr\Relay\Relay('wss://thecitadel.nostr1.com'),
                    new \swentel\nostr\Relay\Relay('wss://relay.damus.io'),
                    new \swentel\nostr\Relay\Relay('wss://relay.nostr.band'),
                    new \swentel\nostr\Relay\Relay('wss://nostr.einundzwanzig.space'),
                    new \swentel\nostr\Relay\Relay('wss://relay.primal.net'),
                    new \swentel\nostr\Relay\Relay('wss://nos.lol'),
                    new \swentel\nostr\Relay\Relay('wss://relay.lumina.rocks'),
                    new \swentel\nostr\Relay\Relay('wss://freelay.sovbit.host'),
                    new \swentel\nostr\Relay\Relay('wss://wheat.happytavern.co'),
                    new \swentel\nostr\Relay\Relay('wss://nostr21.com'),
                    new \swentel\nostr\Relay\Relay('wss://theforest.nostr1.com'),
                    new \swentel\nostr\Relay\Relay('ws://localhost:8080')
                ];
            }
        }
        
        // Log the event kind for better debugging
        echo "Sending event kind " . $eventKind . " to " . count($relays) . " relays..." . PHP_EOL;
        
        // For deletion events (kind 5), provide additional information
        if ($eventKind === 5) {
            echo "Note: Deletion events (kind 5) may be rejected by some relays due to relay policies." . PHP_EOL;
            echo "      Ensure you're using the same private key that created the original event." . PHP_EOL;
        }
        
        $relaySet = new \swentel\nostr\Relay\RelaySet();
        $relaySet->setRelays($relays);
        $relaySet->setMessage($eventMessage);
        
        $maxRetries = 3;
        $retryCount = 0;
        $successfulRelays = [];
        $failedRelays = [];
        
        while ($retryCount < $maxRetries) {
            try {
                // Use our helper function to handle errors
                $result = $this->executeWithErrorHandling(function() use ($relaySet) {
                    return $relaySet->send();
                }, 'RelaySet.php');
                
                // Check if any relays accepted the event
                $anySuccess = false;
                foreach ($result as $relayUrl => $response) {
                    if (is_object($response) && method_exists($response, 'isSuccess')) {
                        $isSuccess = $response->isSuccess();
                        if ($isSuccess) {
                            $anySuccess = true;
                            $successfulRelays[] = $relayUrl;
                        } else {
                            $failedRelays[] = $relayUrl;
                        }
                    }
                }
                
                if ($anySuccess) {
                    echo "Event successfully sent to " . count($successfulRelays) . " relays:" . PHP_EOL;
                    echo "  Accepted by: " . implode(", ", $successfulRelays) . PHP_EOL;
                    if (!empty($failedRelays)) {
                        echo "  Rejected by: " . implode(", ", $failedRelays) . PHP_EOL;
                    }
                    return [
                        'success' => true,
                        'message' => 'Event sent successfully to ' . count($successfulRelays) . ' relays',
                        'event_id' => $eventId,
                        'successful_relays' => $successfulRelays,
                        'failed_relays' => $failedRelays
                    ];
                } else {
                    echo "No relays accepted the event. Retrying..." . PHP_EOL;
                    if (!empty($failedRelays)) {
                        echo "  Rejected by: " . implode(", ", $failedRelays) . PHP_EOL;
                    }
                    $retryCount++;
                    sleep(5);
                }
            } catch (\TypeError $e) {
                echo "Sending to relays did not work. Will be retried." . PHP_EOL;
                $retryCount++;
                sleep(5);
            } catch (\Exception $e) {
                // Handle other exceptions, including invalid status code
                echo "Error sending to relays: " . $e->getMessage() . ". Will be retried." . PHP_EOL;
                $retryCount++;
                sleep(5);
            }
        }
        
        // If we've exhausted all retries, try with just the default relay
        try {
            // Use sovbit relay as the default for kind 1 notes
            $defaultRelay = ($eventKind === 1) ? 'wss://freelay.sovbit.host' : 'wss://thecitadel.nostr1.com';
            
            echo "Trying with default relay: " . $defaultRelay . PHP_EOL;
            
            $singleRelaySet = new \swentel\nostr\Relay\RelaySet();
            $singleRelaySet->setRelays([new \swentel\nostr\Relay\Relay($defaultRelay)]);
            $singleRelaySet->setMessage($eventMessage);
            
            // Use our helper function to handle errors
            $result = $this->executeWithErrorHandling(function() use ($singleRelaySet) {
                return $singleRelaySet->send();
            }, 'RelaySet.php');
            
            // Check if the default relay accepted the event
            $defaultSuccess = false;
            foreach ($result as $relayUrl => $response) {
                if (is_object($response) && method_exists($response, 'isSuccess')) {
                    $isSuccess = $response->isSuccess();
                    if ($isSuccess) {
                        $defaultSuccess = true;
                        $successfulRelays[] = $relayUrl;
                    } else {
                        $failedRelays[] = $relayUrl;
                    }
                }
            }
            
            if ($defaultSuccess) {
                echo "Event successfully sent to default relay:" . PHP_EOL;
                echo "  Accepted by: " . implode(", ", $successfulRelays) . PHP_EOL;
                if (!empty($failedRelays)) {
                    echo "  Rejected by: " . implode(", ", $failedRelays) . PHP_EOL;
                }
                return [
                    'success' => true,
                    'message' => 'Event sent successfully to default relay',
                    'event_id' => $eventId,
                    'successful_relays' => $successfulRelays,
                    'failed_relays' => $failedRelays
                ];
            } else {
                echo "Default relay did not accept the event." . PHP_EOL;
                echo "  Rejected by: " . $defaultRelay . PHP_EOL;
                
                // Provide specific feedback for deletion events
                if ($eventKind === 5) {
                    echo "Deletion event was rejected by all relays. This could be because:" . PHP_EOL;
                    echo "1. The relays don't accept deletion events (kind 5)" . PHP_EOL;
                    echo "2. The private key used doesn't match the original event creator" . PHP_EOL;
                    echo "3. The event ID might not exist on these relays" . PHP_EOL;
                }
                
                return [
                    'success' => false,
                    'message' => 'Event was rejected by all relays including default relay',
                    'event_id' => $eventId,
                    'event_kind' => $eventKind,
                    'failed_relays' => array_merge($failedRelays, [$defaultRelay])
                ];
            }
        } catch (\Exception $e) {
            // If even the default relay fails, return a detailed error response
            echo "All relays including default relay failed with error: " . $e->getMessage() . PHP_EOL;
            
            // Provide specific feedback for deletion events
            if ($eventKind === 5) {
                echo "Deletion event could not be sent to any relay. This could be because:" . PHP_EOL;
                echo "1. The relays don't accept deletion events (kind 5)" . PHP_EOL;
                echo "2. The private key used doesn't match the original event creator" . PHP_EOL;
                echo "3. The event ID might not exist on these relays" . PHP_EOL;
                echo "4. Network or connection issues with the relays" . PHP_EOL;
            }
            
            return [
                'success' => false,
                'message' => 'Failed to send event to any relay: ' . $e->getMessage(),
                'event_id' => $eventId,
                'event_kind' => $eventKind,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute a callback function with error handling
     *
     * @param callable $callback The function to execute
     * @param string $filePattern File pattern to match for error suppression
     * @return mixed The result of the callback function
     */
    protected function executeWithErrorHandling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
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
        } catch (\Exception $e) {
            // Restore the error handler even if an exception occurs
            restore_error_handler();
            throw $e; // Re-throw the exception
        }
    }
    
    /**
     * Logs event data to a file
     *
     * @param string $eventKind The kind of event
     * @param string $eventID The event ID
     * @param string $dTag The d-tag
     * @return bool True if successful, false otherwise
     */
    protected function printEventData(string $eventKind, string $eventID, string $dTag): bool
    {
        $fullpath = getcwd() . "/eventsCreated.yml";
        
        try {
            $fp = fopen($fullpath, "a");
            if (!$fp) {
                error_log("Failed to open event log file: $fullpath");
                return false;
            }
            
            $data = sprintf(
                "event ID: %s%s  event kind: %s%s  d Tag: %s%s",
                $eventID, PHP_EOL,
                $eventKind, PHP_EOL,
                $dTag, PHP_EOL
            );
            
            $result = fwrite($fp, $data);
            fclose($fp);
            
            return $result !== false;
        } catch (\Exception $e) {
            error_log("Error writing to event log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the event kind number
     * 
     * @return int The event kind number
     */
    abstract protected function getEventKind(): int;
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    abstract protected function getEventKindName(): string;
    
    /**
     * Preprocesses the markup content
     * 
     * @param string $markup The raw markup content
     * @return array The processed markup
     * @throws InvalidArgumentException If the markup structure is invalid
     */
    abstract protected function preprocessMarkup(string $markup): array;
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    abstract protected function extractTitleAndCreateDTag(array &$markupFormatted): void;
    
    /**
     * Builds an event with the appropriate tags
     * 
     * @return Event The configured event
     */
    abstract protected function buildEvent(): Event;
}

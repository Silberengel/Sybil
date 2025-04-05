<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;

/**
 * Class PublicationEvent
 * 
 * Represents a publication event in the Nostr protocol.
 * This class handles the creation and publishing of publication events.
 */
class PublicationEvent extends BaseEvent
{
    /**
     * @var array Sections of the publication
     */
    protected array $sections = [];
    
    /**
     * @var array Section events
     */
    protected array $sectionEvents = [];
    
    /**
     * @var array Section event IDs
     */
    protected array $sectionEventIds = [];
    
    /**
     * @var array Section d-tags
     */
    protected array $sectionDTags = [];
    
    /**
     * @var string Tag type (a or e)
     */
    protected string $tagType = 'a';
    
    /**
     * Get the event kind number
     * 
     * @return int The event kind number
     */
    protected function getEventKind(): int
    {
        return 30040;
    }
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    protected function getEventKindName(): string
    {
        return '30040';
    }
    
    /**
     * Preprocesses the markup content
     * 
     * @param string $markup The raw markup content
     * @return array The processed markup
     * @throws InvalidArgumentException If the markup structure is invalid
     */
    protected function preprocessMarkup(string $markup): array
    {
        // Check if the file is an AsciiDoc file
        if (!str_ends_with(strtolower($this->file), '.adoc')) {
            throw new InvalidArgumentException('The file must be an AsciiDoc file (.adoc).');
        }
        
        // Split the markup into sections
        $sections = $this->splitIntoSections($markup);
        
        // Check if we have at least one section
        if (empty($sections)) {
            throw new InvalidArgumentException('The file must have at least one section.');
        }
        
        return $sections;
    }
    
    /**
     * Split the markup into sections
     * 
     * @param string $markup The raw markup content
     * @return array The sections
     */
    private function splitIntoSections(string $markup): array
    {
        // Split the markup into sections based on level 1 headers
        $pattern = '/^=\s+(.*)$/m';
        $sections = preg_split($pattern, $markup, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        // Check if we have YAML metadata
        if (strpos($sections[0], '<<YAML>>') !== false && strpos($sections[0], '<</YAML>>') !== false) {
            // Extract YAML metadata
            $yamlPattern = '/<<YAML>>(.*?)<\/YAML>>/s';
            preg_match($yamlPattern, $sections[0], $yamlMatches);
            
            if (!empty($yamlMatches[1])) {
                $yamlContent = $yamlMatches[1];
                
                // Parse YAML content
                $yamlData = yaml_parse($yamlContent);
                
                // Set title and other metadata
                if (isset($yamlData['title'])) {
                    $this->setTitle($yamlData['title']);
                }
                
                // Set tag type
                if (isset($yamlData['tag-type'])) {
                    $this->tagType = $yamlData['tag-type'];
                }
                
                // Set optional tags
                if (isset($yamlData['tags']) && is_array($yamlData['tags'])) {
                    $this->setOptionalTags($yamlData['tags']);
                }
            }
            
            // Remove YAML metadata from the first section
            $sections[0] = preg_replace($yamlPattern, '', $sections[0]);
        }
        
        // Process sections
        $processedSections = [];
        
        // If we have an odd number of sections, the first one is content without a header
        if (count($sections) % 2 !== 0) {
            $processedSections[] = [
                'title' => 'Introduction',
                'content' => trim($sections[0])
            ];
            
            // Remove the first section
            array_shift($sections);
        }
        
        // Process the remaining sections
        for ($i = 0; $i < count($sections); $i += 2) {
            $title = trim($sections[$i]);
            $content = isset($sections[$i + 1]) ? trim($sections[$i + 1]) : '';
            
            $processedSections[] = [
                'title' => $title,
                'content' => $content
            ];
        }
        
        return $processedSections;
    }
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // If title is not set, use the first section's title
        if (empty($this->title) && !empty($markupFormatted[0]['title'])) {
            $this->setTitle($markupFormatted[0]['title']);
        }
        
        // Create d-tag
        $this->dTag = $this->createDTag($this->title);
        
        // Store the sections
        $this->sections = $markupFormatted;
        
        // Create section events
        $this->createSectionEvents();
    }
    
    /**
     * Create a d-tag from a title
     * 
     * @param string $title The title
     * @return string The d-tag
     */
    private function createDTag(string $title): string
    {
        // Replace spaces with dashes
        $dTag = preg_replace('/\s+/', '-', $title);
        
        // Convert to lowercase
        $dTag = strtolower($dTag);
        
        // Remove special characters
        $dTag = preg_replace('/[^a-z0-9\-]/', '', $dTag);
        
        // Limit to 75 characters
        $dTag = substr($dTag, 0, 75);
        
        return $dTag;
    }
    
    /**
     * Create section events
     * 
     * @return void
     */
    private function createSectionEvents(): void
    {
        // Create a section event for each section
        foreach ($this->sections as $index => $section) {
            // Create section event
            $sectionEvent = new SectionEvent();
            $sectionEvent->setTitle($section['title']);
            $sectionEvent->setContent($section['content']);
            
            // Create d-tag for the section
            $sectionDTag = $this->createDTag($section['title']);
            $sectionEvent->setDTag($sectionDTag);
            
            // Store the section event
            $this->sectionEvents[] = $sectionEvent;
            $this->sectionDTags[] = $sectionDTag;
            
            // Build and publish the section event
            $event = $sectionEvent->build();
            
            // Prepare and send the event using a custom method
            $result = $this->prepareSectionEvent($event);
            
            // Check if the event was published successfully
            $success = isset($result['success']) && $result['success'];
            
            // Record the result
            $this->recordSectionResult($index + 1, count($this->sections), $sectionEvent->getKindName(), $event, $success);
            
            // Store the section event ID
            $this->sectionEventIds[] = $event->getId();
        }
    }
    
    /**
     * Records the result of creating a section event
     *
     * @param int $index The section index
     * @param int $total The total number of sections
     * @param string $kind The event kind
     * @param Event $note The event
     * @param bool $success Whether the event was published successfully
     * @return void
     * @throws InvalidArgumentException If the event ID was not created
     */
    private function recordSectionResult(int $index, int $total, string $kind, Event $note, bool $success = true): void
    {
        // Get event ID with retry
        $eventID = $this->getSectionEventIdWithRetry($note);
        
        // Log the event
        if ($success) {
            echo "Building section $index of $total." . PHP_EOL;
            echo "Debug - Initial Event ID: " . $eventID . PHP_EOL;
            echo "Sending event kind " . $kind . " to 1 relays..." . PHP_EOL;
            echo "Event successfully sent to 1 relays:" . PHP_EOL;
            echo "  Accepted by: wss://thecitadel.nostr1.com" . PHP_EOL . PHP_EOL;
            echo "Published " . $kind . " event with ID " . $eventID . PHP_EOL . PHP_EOL;
        } else {
            echo "Created " . $kind . " event with ID " . $eventID . " but no relay accepted it." . PHP_EOL;
            echo "The event was not published to any relay." . PHP_EOL . PHP_EOL;
        }
    }
    
    /**
     * Builds an event with the appropriate tags
     * 
     * @return Event The configured event
     */
    protected function buildEvent(): Event
    {
        // Create event
        $event = new Event();
        $event->setKind($this->getEventKind());
        $event->setContent($this->content);
        
        // Add tags
        $tags = [
            ['d', $this->dTag],
            ['title', $this->title]
        ];
        
        // Add section references
        if ($this->tagType === 'e') {
            // Add e-tags
            foreach ($this->sectionEventIds as $sectionEventId) {
                $tags[] = ['e', $sectionEventId];
            }
            
            // Add tag type
            $tags[] = ['t', 'e-tags'];
        } else {
            // Add a-tags
            $publicHex = $this->getPublicHexKey();
            
            foreach ($this->sectionDTags as $index => $sectionDTag) {
                $tags[] = ['a', $sectionDTag . ':' . $publicHex . ':30041', self::DEFAULT_RELAY, 'wss'];
            }
            
            // Add tag type
            $tags[] = ['t', 'a-tags'];
        }
        
        // Add optional tags
        foreach ($this->optionalTags as $tag) {
            $tags[] = $tag;
        }
        
        // Set tags
        $event->setTags($tags);
        
        return $event;
    }
    
    /**
     * Get the public hex key
     * 
     * @return string The public hex key
     */
    private function getPublicHexKey(): string
    {
        // Get private key from environment
        $privateBech32 = getenv('NOSTR_SECRET_KEY');
        
        // Convert to hex and get public key
        $keys = new \swentel\nostr\Key\Key();
        $privateHex = $keys->convertToHex(key: $privateBech32);
        return $keys->getPublicKey(private_hex: $privateHex);
    }
    
    /**
     * Sends an event to relays
     * 
     * @param Event $event The event to send
     * @return array The result of sending the event
     */
    public function sendEvent(Event $event): array
    {
        return $this->prepareEventData($event);
    }
    
    /**
     * Prepares and sends a section event
     * 
     * @param Event $note The event to prepare and send
     * @return array The result of sending the event
     * @throws InvalidArgumentException If the private key is missing or invalid
     */
    private function prepareSectionEvent(Event $note): array
    {
        // Get private key from environment
        $privateKey = getenv('NOSTR_SECRET_KEY');
        
        // Validate private key
        if (!str_starts_with($privateKey, 'nsec')) {
            throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.');
        }
        
        // Get the event kind
        $kind = 0;
        if (method_exists($note, 'getKind')) {
            $kind = $note->getKind();
        }
        
        // Get relay list for this kind of event
        $relays = $this->getSectionRelayList($kind);
        
        // Sign the event
        $signer = new \swentel\nostr\Sign\Sign();
        $signer->signEvent($note, $privateKey);
        
        // Create event message
        $eventMessage = new \swentel\nostr\Message\EventMessage($note);
        
        // Send the event with retry on failure, passing the custom relay list
        return $this->sendSectionEventWithRetry($eventMessage, $relays);
    }
    
    /**
     * Gets the list of relays from the configuration file for section events
     *
     * @param int $kind Optional event kind to get relays for
     * @param array $preferredRelays Optional array of preferred relay URLs to use if available
     * @return array An array of Relay objects
     */
    private function getSectionRelayList(int $kind = 0, array $preferredRelays = []): array
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
     * Sends a section event with retry on failure
     *
     * @param \swentel\nostr\Message\EventMessage $eventMessage The event message to send
     * @param array $customRelays Optional array of Relay objects to use instead of the default list
     * @return array The result from sending the event
     */
    private function sendSectionEventWithRetry(\swentel\nostr\Message\EventMessage $eventMessage, array $customRelays = []): array
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
        
        // Debug output
        echo "Debug - Initial Event ID: " . $eventId . PHP_EOL;
        
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
                $result = $this->executeSectionWithErrorHandling(function() use ($relaySet) {
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
            $result = $this->executeSectionWithErrorHandling(function() use ($singleRelaySet) {
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
     * Execute a callback function with error handling for section events
     *
     * @param callable $callback The function to execute
     * @param string $filePattern File pattern to match for error suppression
     * @return mixed The result of the callback function
     */
    private function executeSectionWithErrorHandling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
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
     * Gets an event ID with retry for section events
     *
     * @param Event $note The event
     * @param int $maxRetries Maximum number of retries
     * @param int $delay Delay between retries in seconds
     * @return string The event ID
     * @throws InvalidArgumentException If the event ID could not be created
     */
    private function getSectionEventIdWithRetry(
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
}

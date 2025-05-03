<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;
use Sybil\Utilities\KeyUtility;
use Sybil\Utilities\TagUtility;
use Sybil\Utilities\RelayUtility;
use Sybil\Utilities\ErrorHandlingUtility;
use Sybil\Utilities\EventPreparationUtility;
use Sybil\Service\LoggerService;

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
     * @var array Hashtags for the publication
     */
    protected array $hashtags = [];
    
    /**
     * @var string Author of the publication
     */
    protected string $author = '';
    
    /**
     * @var string Version of the publication
     */
    protected string $version = '1';
    
    /**
     * @var LoggerService
     */
    protected LoggerService $logger;
    
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
        
        // Extract YAML metadata
        if (strpos($markup, '<<YAML>>') !== false) {
            // Extract YAML metadata - handle both with and without comment markers
            $yamlPattern = '/(?:\/\/\/\/\s*)?<<YAML>>(.*?)<<\/YAML>>(?:\s*\/\/\/\/)?/s';
            preg_match($yamlPattern, $markup, $yamlMatches);
            
            if (!empty($yamlMatches[1])) {
                $yamlContent = $yamlMatches[1];
                
                // Parse YAML content
                $yamlData = yaml_parse($yamlContent);
                
                // Set title and other metadata
                if (isset($yamlData['title'])) {
                    $this->setTitle($yamlData['title']);
                }
                
                if (isset($yamlData['author'])) {
                    $this->author = $yamlData['author'];
                }
                
                if (isset($yamlData['version'])) {
                    $this->version = $yamlData['version'];
                }
                
                if (isset($yamlData['tag-type'])) {
                    $this->tagType = $yamlData['tag-type'];
                }
                
                // Process tags
                if (isset($yamlData['tags']) && is_array($yamlData['tags'])) {
                    foreach ($yamlData['tags'] as $tag) {
                        if (is_array($tag) && count($tag) >= 2) {
                            if ($tag[0] === 't') {
                                $this->hashtags[] = $tag[1];
                            } else {
                                $this->optionalTags[] = $tag;
                            }
                        }
                    }
                }
            }
        }
        
        // Remove YAML frontmatter by removing lines between //// markers
        $lines = explode("\n", $markup);
        $cleanedLines = [];
        $inYamlBlock = false;
        
        foreach ($lines as $line) {
            if (strpos($line, '////') !== false) {
                $inYamlBlock = !$inYamlBlock;
                continue;
            }
            
            if (!$inYamlBlock) {
                $cleanedLines[] = $line;
            }
        }
        
        $markup = implode("\n", $cleanedLines);
        
        // Set content
        $this->content = $markup;
        
        return [
            [
                'title' => $this->title,
                'content' => $markup
            ]
        ];
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
     * @param bool $isSection Whether this is a section d-tag
     * @return string The d-tag
     */
    private function createDTag(string $title, bool $isSection = false): string
    {
        // Replace spaces with dashes
        $dTag = preg_replace('/\s+/', '-', $title);
        
        // Convert to lowercase
        $dTag = strtolower($dTag);
        
        // Remove special characters
        $dTag = preg_replace('/[^a-z0-9\-]/', '', $dTag);
        
        // If this is a section, prefix with the parent d-tag
        if ($isSection && !empty($this->dTag)) {
            $dTag = $this->dTag . '-' . $dTag;
        }
        
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
        // Log operation start with appropriate level
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
            $this->logger->info("Creating section events for " . count($this->sections) . " sections");
        }
        
        // Create a section event for each section
        foreach ($this->sections as $index => $section) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Processing section " . ($index + 1) . ": " . $section['title']);
                $this->logger->debug("  Content length: " . strlen($section['content']) . " bytes");
            }
            
            // Create section event
            $sectionEvent = new SectionEvent();
            $sectionEvent->setTitle($section['title']);
            $sectionEvent->setContent($section['content']);
            
            // Create specific tags for section events
            $sectionTags = $this->optionalTags;
            
            // Add 'm' and 'M' tags for section events
            $sectionTags[] = ['m', 'text/asciidoc'];
            $sectionTags[] = ['M', 'article/publication-content/replaceable'];
            
            // Pass tags to the section event
            $sectionEvent->setOptionalTags($sectionTags);
            
            // Create d-tag for the section, passing true to indicate it's a section
            $sectionDTag = $this->createDTag($section['title'], true);
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
            $eventId = $event->getId();
            $this->sectionEventIds[] = $eventId;
            
            echo "Added section event ID: " . $eventId . PHP_EOL;
        }
        
        echo "Finished creating section events. Total sections: " . count($this->sections) . PHP_EOL;
        echo "Total section events: " . count($this->sectionEvents) . PHP_EOL;
        echo "Total section event IDs: " . count($this->sectionEventIds) . PHP_EOL;
        echo "Total section d-tags: " . count($this->sectionDTags) . PHP_EOL;
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
        
        // Log the event with appropriate levels
        if ($success) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Published section " . ($index + 1) . " of " . $total . " (" . $kind . ") with ID " . $eventID);
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Section details:");
                $this->logger->debug("  Section: " . ($index + 1) . " of " . $total);
                $this->logger->debug("  Kind: " . $kind);
                $this->logger->debug("  Event ID: " . $eventID);
                $this->logger->debug("  URL: https://njump.me/" . $eventID);
            }
        } else {
            $this->logger->error("Created section " . ($index + 1) . " of " . $total . " (" . $kind . ") with ID " . $eventID . " but no relay accepted it.");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed section details:");
                $this->logger->debug("  Section: " . ($index + 1) . " of " . $total);
                $this->logger->debug("  Kind: " . $kind);
                $this->logger->debug("  Event ID: " . $eventID);
            }
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
        $tags = [];
        
        // Add d-tag
        if (!empty($this->dTag)) {
            $tags[] = ['d', $this->dTag];
        }
        
        // Add title tag
        if (!empty($this->title)) {
            $tags[] = ['title', $this->title];
        }
        
        // Add author tag
        if (!empty($this->author)) {
            $tags[] = ['author', $this->author];
        }
        
        // Add version tag
        if (!empty($this->version)) {
            $tags[] = ['version', $this->version];
        }
        
        // Add section tags
        foreach ($this->sectionEventIds as $sectionEventId) {
            $tags[] = [$this->tagType, $sectionEventId];
        }
        
        // Add hashtags
        foreach ($this->hashtags as $hashtag) {
            $tags[] = ['t', $hashtag];
        }
        
        // Add optional tags
        foreach ($this->optionalTags as $tag) {
            $tags[] = $tag;
        }
        
        // Set tags
        $event->setTags($tags);
        
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Built publication event:");
            $this->logger->debug("  Kind: " . $this->getEventKind());
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  D-Tag: " . $this->dTag);
            $this->logger->debug("  Author: " . $this->author);
            $this->logger->debug("  Version: " . $this->version);
            $this->logger->debug("  Sections: " . count($this->sectionEventIds));
            $this->logger->debug("  Hashtags: " . count($this->hashtags));
            $this->logger->debug("  Optional tags: " . count($this->optionalTags));
        }
        
        return $event;
    }
    
    /**
     * Sends an event to relays
     * 
     * @param Event $event The event to send
     * @return array The result of sending the event
     */
    public function sendEvent(Event $event): array
    {
        // Log operation start with appropriate level
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
            $this->logger->info("Sending publication event");
        }
        
        // Create event message
        $eventMessage = new \swentel\nostr\Message\EventMessage($event);
        
        // Send the event with retry on failure
        $result = $this->sendEventWithRetry($eventMessage);
        
        // Log the result with appropriate level
        if ($result['success']) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Publication event sent successfully");
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Send result:");
                $this->logger->debug("  Event ID: " . $event->getId());
                $this->logger->debug("  Response: " . json_encode($result));
            }
        } else {
            $this->logger->error("Failed to send publication event");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed send details:");
                $this->logger->debug("  Event ID: " . $event->getId());
                $this->logger->debug("  Error: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        return $result;
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
        $privateKey = KeyUtility::getNsec();
        
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
        return ErrorHandlingUtility::executeWithErrorHandling($callback, $filePattern);
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

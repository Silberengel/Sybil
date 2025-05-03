<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;
use Sybil\Service\LoggerService;

/**
 * Class SectionEvent
 * 
 * Represents a section event in the Nostr protocol.
 * This class handles the creation and publishing of section events.
 */
class SectionEvent extends BaseEvent
{
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
        return 30041;
    }
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    protected function getEventKindName(): string
    {
        return '30041';
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
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Processing section content:");
            $this->logger->debug("  Content length: " . strlen($markup) . " bytes");
        }
        
        // For section events, we don't need to preprocess the markup
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
        // If title is not set, use a default title
        if (empty($this->title)) {
            $this->setTitle('Section');
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Using default title: Section");
            }
        }
        
        // Create d-tag
        $this->dTag = $this->createDTag($this->title);
        
        // Set content
        $this->content = $markupFormatted[0]['content'];
        
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Processed section:");
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  D-Tag: " . $this->dTag);
            $this->logger->debug("  Content length: " . strlen($this->content) . " bytes");
        }
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
        
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Created d-tag from title:");
            $this->logger->debug("  Original title: " . $title);
            $this->logger->debug("  Generated d-tag: " . $dTag);
        }
        
        return $dTag;
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
        
        // Add optional tags
        foreach ($this->optionalTags as $tag) {
            $tags[] = $tag;
        }
        
        // Set tags
        $event->setTags($tags);
        
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Built section event:");
            $this->logger->debug("  Kind: " . $this->getEventKind());
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  D-Tag: " . $this->dTag);
            $this->logger->debug("  Optional tags: " . count($this->optionalTags));
            $this->logger->debug("  Content length: " . strlen($this->content) . " bytes");
        }
        
        return $event;
    }
    
    /**
     * Gets the event kind name
     * 
     * @return string The event kind name
     */
    public function getKindName(): string
    {
        return $this->getEventKindName();
    }
    
    /**
     * Builds and returns an event
     * 
     * @return Event The configured event
     */
    public function build(): Event
    {
        return $this->buildEvent();
    }
    
    /**
     * Publishes the event to a relay
     * 
     * @param string $relayUrl The relay URL
     * @param string|null $keyEnvVar The environment variable name for the private key
     * @return array The result of the publish operation
     */
    public function publishToRelay(string $relayUrl, ?string $keyEnvVar = null): array
    {
        // Build the event
        $event = $this->buildEvent();
        
        // Get private key from environment
        $utility = new KeyUtility();
        $privateKey = $utility::getNsec($keyEnvVar);
        
        // Sign the event
        $signer = new \swentel\nostr\Sign\Sign();
        $signer->signEvent($event, $privateKey);
        
        // Get the event ID
        $eventId = $event->getId();
        
        // Log operation start with appropriate level
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
            $this->logger->info("Publishing section to relay {$relayUrl}");
        }
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Section details:");
            $this->logger->debug("  Event ID: " . $eventId);
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  Content: " . substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : ''));
        }
        
        // Create event message
        $eventMessage = new \swentel\nostr\Message\EventMessage($event);
        
        // Create relay
        $relay = new Relay($relayUrl);
        
        // Send the event with retry on failure, passing the custom relay list
        $result = $this->sendEventWithRetry($eventMessage, [$relay]);
        
        // Add the event ID to the result
        $result['event_id'] = $eventId;
        
        // Log the result with appropriate level
        if ($result['success']) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Section published successfully to relay {$relayUrl}");
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Publish result:");
                $this->logger->debug("  Event ID: " . $eventId);
                $this->logger->debug("  Relay: " . $relayUrl);
                $this->logger->debug("  Response: " . json_encode($result));
            }
        } else {
            $this->logger->error("Failed to publish section to relay {$relayUrl}");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed publish details:");
                $this->logger->debug("  Event ID: " . $eventId);
                $this->logger->debug("  Relay: " . $relayUrl);
                $this->logger->debug("  Error: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        return $result;
    }
}

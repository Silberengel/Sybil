<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use swentel\nostr\Relay\Relay;
use InvalidArgumentException;
use Sybil\Utilities\KeyUtility;
use Sybil\Utilities\TagUtility;
use Sybil\Utilities\RelayUtility;
use Sybil\Utilities\ErrorHandlingUtility;
use Sybil\Utilities\EventPreparationUtility;
use Sybil\Service\LoggerService;

/**
 * Class TextNoteEvent
 * 
 * Represents a text note event in the Nostr protocol.
 * This class handles the creation and publishing of text note events.
 */
class TextNoteEvent extends BaseEvent
{
    /**
     * Constructor
     *
     * @param string $content Content for the text note
     */
    public function __construct(string $content)
    {
        parent::__construct();
        $this->setContent($content);
    }
    
    /**
     * Get the event kind number
     * 
     * @return int The event kind number
     */
    protected function getEventKind(): int
    {
        return 1;
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
     * 
     * @param string $markup The raw markup content
     * @return array The processed markup
     * @throws InvalidArgumentException If the markup structure is invalid
     */
    protected function preprocessMarkup(string $markup): array
    {
        // Check if the file is a text or markdown file
        if (!empty($this->file)) {
            $extension = strtolower(pathinfo($this->file, PATHINFO_EXTENSION));
            if (!in_array($extension, ['txt', 'md'])) {
                throw new InvalidArgumentException('The file must be a text file (.txt) or markdown file (.md).');
            }
        }
        
        // For text notes, we don't need to preprocess the markup
        return [
            [
                'title' => '',
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
        // For text notes, we don't need a d-tag
        $this->dTag = '';
        
        // Set content
        if (empty($this->content)) {
            $this->content = $markupFormatted[0]['content'];
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
        
        // Extract hashtags from content
        $hashtags = $this->extractHashtags($this->content);
        
        // Add hashtags
        foreach ($hashtags as $hashtag) {
            $tags[] = ['t', $hashtag];
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
     * Extract hashtags from content
     * 
     * @param string $content The content
     * @return array The hashtags
     */
    private function extractHashtags(string $content): array
    {
        $hashtags = [];
        
        // Extract hashtags
        preg_match_all('/#(\w+)/', $content, $matches);
        
        if (!empty($matches[1])) {
            $hashtags = $matches[1];
        }
        
        return $hashtags;
    }
    
    /**
     * Publish the text note to a specific relay
     * 
     * @param string $relayUrl The relay URL
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @return array The result of sending the event
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
            $this->logger->info("Publishing text note to relay {$relayUrl}");
        }
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Text note details:");
            $this->logger->debug("  Event ID: " . $eventId);
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
                $this->logger->info("Text note published successfully to relay {$relayUrl}");
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Publish result:");
                $this->logger->debug("  Event ID: " . $eventId);
                $this->logger->debug("  Relay: " . $relayUrl);
                $this->logger->debug("  Response: " . json_encode($result));
            }
        } else {
            $this->logger->error("Failed to publish text note to relay {$relayUrl}");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed publish details:");
                $this->logger->debug("  Event ID: " . $eventId);
                $this->logger->debug("  Relay: " . $relayUrl);
                $this->logger->debug("  Error: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        return $result;
    }

    /**
     * Publish the text note
     * 
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @return bool True if the event was published successfully, false otherwise
     */
    public function publish(?string $keyEnvVar = null): bool
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
        
        // Log operation start with appropriate level
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
            $this->logger->info("Publishing text note");
        }
        
        // Prepare and send the event
        $result = $this->prepareEventData($event, $keyEnvVar);
        
        // Check if the event was published successfully
        $success = isset($result['success']) && $result['success'];
        
        // Handle the result with appropriate logging levels
        if ($success) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Text note published successfully");
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Publish details:");
                $this->logger->debug("  Event ID: " . $event->getId());
                $this->logger->debug("  Content: " . substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : ''));
                $this->logger->debug("  Result: " . json_encode($result));
            }
        } else {
            $this->logger->error("Failed to publish text note");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed publish details:");
                $this->logger->debug("  Event ID: " . $event->getId());
                $this->logger->debug("  Content: " . substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : ''));
                $this->logger->debug("  Error: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        // Record the result
        $this->recordResult($this->getEventKindName(), $event, $success);
        
        return $success;
    }

    protected function recordResult(string $kind, Event $note, bool $success = true): void
    {
        // Get event ID with retry
        $eventID = $this->getEventIdWithRetry($note);
        
        // Log the event with appropriate levels
        if ($success) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Published " . $kind . " event with ID " . $eventID);
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Event details:");
                $this->logger->debug("  Kind: " . $kind);
                $this->logger->debug("  Event ID: " . $eventID);
                $this->logger->debug("  Content: " . substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : ''));
                $this->logger->debug("  URL: https://njump.me/" . $eventID);
            }
        } else {
            $this->logger->error("Created " . $kind . " event with ID " . $eventID . " but no relay accepted it.");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed event details:");
                $this->logger->debug("  Kind: " . $kind);
                $this->logger->debug("  Event ID: " . $eventID);
                $this->logger->debug("  Content: " . substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : ''));
            }
        }
    }
}

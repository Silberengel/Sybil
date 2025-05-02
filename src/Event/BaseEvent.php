<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;
use Sybil\Utilities\RelayUtility;
use Sybil\Utilities\ErrorHandlingUtility;
use Sybil\Utilities\LogUtility;
use Sybil\Utilities\EventPreparationUtility;

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
        return EventPreparationUtility::prepareEventData($note);
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
        return RelayUtility::getRelayList($kind, $preferredRelays);
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
        return RelayUtility::sendEventWithRetry($eventMessage, $customRelays);
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
        return ErrorHandlingUtility::executeWithErrorHandling($callback, $filePattern);
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
        return LogUtility::logEventData($eventKind, $eventID, $dTag);
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

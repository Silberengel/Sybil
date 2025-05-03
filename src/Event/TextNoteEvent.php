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
     * @param string $content Optional content for the text note
     */
    public function __construct(string $content = '')
    {
        parent::__construct();
        
        if (!empty($content)) {
            if (is_file($content)) {
                $this->setContent(file_get_contents($content));
            } else {
                $this->setContent($content);
            }
        }
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
     * @return array The result of sending the event
     */
    public function publishToRelay(string $relayUrl): array
    {
        // Build the event
        $event = $this->buildEvent();
        
        // Get private key from environment
        $utility = new KeyUtility();
        $privateKey = $utility::getNsec();
        
        // Sign the event
        $signer = new \swentel\nostr\Sign\Sign();
        $signer->signEvent($event, $privateKey);
        
        // Get the event ID
        $eventId = $event->getId();
        
        // Output the event ID
        echo "Event ID: " . $eventId . PHP_EOL;
        
        // Create event message
        $eventMessage = new \swentel\nostr\Message\EventMessage($event);
        
        // Create relay
        $relay = new Relay($relayUrl);
        
        // Send the event with retry on failure, passing the custom relay list
        $result = $this->sendEventWithRetry($eventMessage, [$relay]);
        
        // Add the event ID to the result
        $result['event_id'] = $eventId;
        
        return $result;
    }
}

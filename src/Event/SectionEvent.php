<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;

/**
 * Class SectionEvent
 * 
 * Represents a section event in the Nostr protocol.
 * This class handles the creation and publishing of section events.
 */
class SectionEvent extends BaseEvent
{
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
        }
        
        // Create d-tag
        $this->dTag = $this->createDTag($this->title);
        
        // Set content
        $this->content = $markupFormatted[0]['content'];
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
        
        // Add optional tags
        foreach ($this->optionalTags as $tag) {
            $tags[] = $tag;
        }
        
        // Set tags
        $event->setTags($tags);
        
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
}

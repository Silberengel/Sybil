<?php

use swentel\nostr\Event\Event;

/**
 * Class WikiEvent
 * 
 * Handles the creation and publishing of wiki page events in the Nostr protocol.
 * This class processes AsciiDoc files, extracts metadata, and then creates a wiki event.
 * Extends BaseEvent to leverage common event handling functionality.
 */
class WikiEvent extends BaseEvent
{
    // Constants
    public const EVENT_KIND = '30818';
    
    /**
     * Get the event kind number
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
        return "wiki";
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
        // Validate header count
        if (!str_contains($markup, '= ')) {
            throw new InvalidArgumentException(
                'This markup file contains no headers. It must have at least one = header. 
                Please correct and retry.');
        }

        // Validate metadata
        if (!str_contains($markup, '<<YAML>>')) {
            throw new InvalidArgumentException(
                'This markup file contains no metadata. Please correct and retry.');
        }

        // Break the file into metadata and sections
                
        return explode("= ", $markup);
        
    }
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // Extract title from the first section after the YAML metadata
        // The first element (index 0) contains the YAML metadata
        // The second element (index 1) contains the title
        if (!isset($markupFormatted[1])) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Missing title section.');
        }
        
        // Extract the title from the second section
        $this->title = trim($markupFormatted[1]);
        
        // Extract YAML from the first section
        $firstSection = explode('////', $markupFormatted[0]);
        
        // Make sure we have at least two parts (before and after the //// delimiter)
        if (count($firstSection) < 2) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Either the content 
                or the metadata is missing.');
        }
        
        // Get the YAML tags
        $yamlTags = create_tags_from_yaml($firstSection[1]);
        
        // Store optional tags
        $this->optionaltags = $yamlTags['tags'];
        
        // Set the content to be everything after the title
        // Join all sections after the title with "= " to restore the original format
        $contentSections = array_slice($markupFormatted, 2);
        $this->content = "= " . implode("= ", $contentSections);
        
        // Extract the first header as the title
        $titleLines = explode("\n", $markupFormatted[1]);
        $headerTitle = trim($titleLines[0]);
        
        // Clean up the header title by removing newlines, extra spaces, and trailing "="
        $headerTitle = trim(preg_replace('/\s+/', ' ', $headerTitle));
        $headerTitle = rtrim($headerTitle, '= ');
        
        // Use the title from the YAML metadata if available, otherwise use the first header
        if (!empty($yamlTags['title'])) {
            $this->title = $yamlTags['title'];
        } else {
            $this->title = $headerTitle;
        }
        
        // Create d-tag - custom implementation for wiki events
        // Get the normalized title
        $normalizedTitle = normalize_tag_component($this->title);
        
        // Format and limit to 50 characters
        $formattedTitle = format_d_tag($normalizedTitle);
        
        // Create the d-tag
        $this->dTag = substr($formattedTitle, 0, 50);
        
        // Clean up
        unset($firstSection);
        unset($yamlTags);
        unset($contentSections);
        unset($markupFormatted);

        echo PHP_EOL;
    }
    
    /**
     * Builds an event with the appropriate tags
     * 
     * @return Event The configured event
     */
    protected function buildEvent(): Event
    {
        // Initialize tags array
        $tags = [];
        
        // Add the d-tag
        $tags[] = ['d', $this->dTag];
        
        // Add the title tag
        $tags[] = ['title', $this->title];
        
        // Add the published_at tag with current timestamp
        $tags[] = ['published_at', (string)time()];
        
        // Add all optional tags
        foreach ($this->optionaltags as $tag) {
            $tags[] = $tag;
        }
        
        // Create the event
        $note = new Event();
        $note->setKind(self::EVENT_KIND);
        $note->setTags($tags);
        $note->setContent($this->content);
        
        return $note;
    }
}

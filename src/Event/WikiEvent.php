<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;

/**
 * Class WikiEvent
 * 
 * Represents a wiki page event in the Nostr protocol.
 * This class handles the creation and publishing of wiki page events.
 */
class WikiEvent extends BaseEvent
{
    /**
     * @var string The author of the wiki page
     */
    protected string $author = '';
    
    /**
     * @var string The summary of the wiki page
     */
    protected string $summary = '';
    
    /**
     * @var array The hashtags for the wiki page
     */
    protected array $hashtags = [];
    
    /**
     * Get the event kind number
     * 
     * @return int The event kind number
     */
    protected function getEventKind(): int
    {
        return 30818;
    }
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    protected function getEventKindName(): string
    {
        return 'wiki';
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
                
                if (isset($yamlData['summary'])) {
                    $this->summary = $yamlData['summary'];
                }
                
                // Process tags
                if (isset($yamlData['tags']) && is_array($yamlData['tags'])) {
                    foreach ($yamlData['tags'] as $tag) {
                        if (is_array($tag) && count($tag) >= 2) {
                            if ($tag[0] === 't') {
                                $this->hashtags[] = $tag[1];
                            } else if ($tag[0] === 'summary') {
                                $this->summary = $tag[1];
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
        
        // If title is not set, extract it from the content
        if (empty($this->title)) {
            // Look for the first heading
            $pattern = '/^=\s+(.*)$/m';
            preg_match($pattern, $markup, $matches);
            
            if (!empty($matches[1])) {
                $this->setTitle($matches[1]);
            } else {
                // Use a default title
                $this->setTitle('Wiki Page');
            }
        }
        
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
        // Create d-tag
        $this->dTag = $this->createDTag($this->title);
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
        
        // Add author tag
        if (!empty($this->author)) {
            $tags[] = ['author', $this->author];
        }
        
        // Add summary tag
        if (!empty($this->summary)) {
            $tags[] = ['summary', $this->summary];
        }
        
        // Add hashtags
        foreach ($this->hashtags as $hashtag) {
            $tags[] = ['t', $hashtag];
        }
        
        // Add published_at tag with Unix timestamp as a string
        $tags[] = ['published_at', (string)time()];
        
        // Add optional tags
        foreach ($this->optionalTags as $tag) {
            $tags[] = $tag;
        }
        
        // Set tags
        $event->setTags($tags);
        
        return $event;
    }
}

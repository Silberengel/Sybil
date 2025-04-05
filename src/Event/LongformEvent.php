<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;

/**
 * Class LongformEvent
 * 
 * Represents a longform article event in the Nostr protocol.
 * This class handles the creation and publishing of longform article events.
 */
class LongformEvent extends BaseEvent
{
    /**
     * @var string The author of the longform article
     */
    protected string $author = '';
    
    /**
     * @var string The summary of the longform article
     */
    protected string $summary = '';
    
    /**
     * @var string The image URL for the longform article
     */
    protected string $image = '';
    
    /**
     * @var array The hashtags for the longform article
     */
    protected array $hashtags = [];
    
    /**
     * Get the event kind number
     * 
     * @return int The event kind number
     */
    protected function getEventKind(): int
    {
        return 30023;
    }
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    protected function getEventKindName(): string
    {
        return 'longform';
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
        // Check if the file is a Markdown file
        if (!str_ends_with(strtolower($this->file), '.md')) {
            throw new InvalidArgumentException('The file must be a Markdown file (.md).');
        }
        
        // Extract metadata from the front matter
        $frontMatter = $this->extractFrontMatter($markup);
        
        // Set metadata
        if (isset($frontMatter['title'])) {
            $this->setTitle($frontMatter['title']);
        }
        
        if (isset($frontMatter['author'])) {
            $this->author = $frontMatter['author'];
        }
        
        if (isset($frontMatter['summary'])) {
            $this->summary = $frontMatter['summary'];
        }
        
        if (isset($frontMatter['image'])) {
            $this->image = $frontMatter['image'];
        }
        
        if (isset($frontMatter['hashtags']) && is_array($frontMatter['hashtags'])) {
            $this->hashtags = $frontMatter['hashtags'];
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
     * Extract front matter from Markdown content
     * 
     * @param string $markup The Markdown content
     * @return array The front matter
     */
    private function extractFrontMatter(string $markup): array
    {
        // First check for standard Markdown front matter
        if (strpos($markup, '---') === 0) {
            // Extract front matter
            $pattern = '/^---\s*\n(.*?)\n---\s*\n/s';
            preg_match($pattern, $markup, $matches);
            
            if (!empty($matches[1])) {
                // Parse YAML front matter
                $frontMatter = yaml_parse($matches[1]);
                return $frontMatter ?: [];
            }
        }
        
        // Then check for YAML metadata with <<YAML>> tags
        if (strpos($markup, '<<YAML>>') !== false) {
            // Extract YAML metadata - handle both with and without comment markers
            $yamlPattern = '/(?:\/\/\/\/\s*)?<<YAML>>(.*?)<<\/YAML>>(?:\s*\/\/\/\/)?/s';
            preg_match($yamlPattern, $markup, $yamlMatches);
            
            if (!empty($yamlMatches[1])) {
                $yamlContent = $yamlMatches[1];
                
                // Parse YAML content
                $yamlData = yaml_parse($yamlContent);
                
                // Process tags
                if (isset($yamlData['tags']) && is_array($yamlData['tags'])) {
                    foreach ($yamlData['tags'] as $tag) {
                        if (is_array($tag) && count($tag) >= 2) {
                            if ($tag[0] === 'image') {
                                $this->image = $tag[1];
                            } else if ($tag[0] === 'summary') {
                                $this->summary = $tag[1];
                            } else if ($tag[0] === 't') {
                                $this->hashtags[] = $tag[1];
                            } else {
                                $this->optionalTags[] = $tag;
                            }
                        }
                    }
                }
                
                return $yamlData ?: [];
            }
        }
        
        return [];
    }
    
    /**
     * Remove front matter from Markdown content
     * 
     * @param string $markup The Markdown content
     * @return string The content without front matter
     */
    private function removeFrontMatter(string $markup): string
    {
        // First check for standard Markdown front matter
        if (strpos($markup, '---') === 0) {
            // Remove front matter
            $pattern = '/^---\s*\n.*?\n---\s*\n/s';
            $markup = preg_replace($pattern, '', $markup);
        }
        
        // Then check for YAML metadata with <<YAML>> tags
        if (strpos($markup, '<<YAML>>') !== false) {
            // Debug output
            echo "Original content length: " . strlen($markup) . PHP_EOL;
            
            // Try a simpler approach - just remove the first 10 lines if they contain YAML
            $lines = explode("\n", $markup);
            $firstTenLines = implode("\n", array_slice($lines, 0, 10));
            
            if (strpos($firstTenLines, '<<YAML>>') !== false) {
                // Find the line number where //// appears after <<YAML>>
                $yamlStartLine = 0;
                $yamlEndLine = 0;
                
                for ($i = 0; $i < count($lines); $i++) {
                    if (strpos($lines[$i], '////') !== false && $yamlStartLine === 0) {
                        $yamlStartLine = $i;
                    } else if (strpos($lines[$i], '////') !== false && $yamlStartLine > 0) {
                        $yamlEndLine = $i;
                        break;
                    }
                }
                
                if ($yamlStartLine > 0 && $yamlEndLine > 0) {
                    // Remove the lines between yamlStartLine and yamlEndLine (inclusive)
                    array_splice($lines, $yamlStartLine, $yamlEndLine - $yamlStartLine + 1);
                    $markup = implode("\n", $lines);
                    echo "Removed YAML frontmatter: " . $yamlStartLine . " to " . $yamlEndLine . PHP_EOL;
                }
            }
            
            echo "New content length: " . strlen($markup) . PHP_EOL;
        }
        
        return $markup;
    }
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // If title is not set, extract it from the content
        if (empty($this->title)) {
            // Look for the first heading
            $pattern = '/^#\s+(.*)$/m';
            preg_match($pattern, $markupFormatted[0]['content'], $matches);
            
            if (!empty($matches[1])) {
                $this->setTitle($matches[1]);
            } else {
                // Use a default title
                $this->setTitle('Untitled');
            }
        }
        
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
        
        // Add image tag
        if (!empty($this->image)) {
            $tags[] = ['image', $this->image];
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

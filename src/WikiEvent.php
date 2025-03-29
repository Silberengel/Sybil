<?php

use swentel\nostr\Event\Event;

/**
 * Class WikiEvent
 * 
 * Handles the creation and publishing of wiki page events in the Nostr protocol.
 * This class processes Markdown files, extracts metadata, and then creates a wiki event.
 */
class WikiEvent
{
    // Wiki properties
    public string $file = '';
    public string $dTag = '';
    public string $title = '';
    public string $content = '';
    public array $optionaltags = [];
    
    // Constants
    public const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';
    public const EVENT_KIND = '30818';
    
/**
 * Constructor for WikiEvent
 * 
 * @param array $data Optional initial data for the wiki page.
 */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            if (isset($data['title'])) {
                $this->title = $data['title'];
            }
            
            if (isset($data['dTag'])) {
                $this->dTag = $data['dTag'];
            }
        }
    }

    /**
     * Create the wiki event
     * 
     * @return void
     * @throws InvalidArgumentException If the file is invalid or has formatting issues
     */
    public function publish(): void
    {
        // Load and validate the markup file
        $markup = $this->loadMarkupFile();
        
        // Process the markup content
        $markupFormatted = $this->preprocessMarkup($markup);
        unset($markup);

        // Extract title and create d-tag
        $this->extractTitleAndCreateDTag($markupFormatted);
        
    }
    
    /**
     * Loads and validates the markup file
     * 
     * @return string The markup content
     * @throws InvalidArgumentException If the file is invalid
     */
    private function loadMarkupFile(): string
    {
        $markup = file_get_contents($this->file);
        if (!$markup) {
            throw new InvalidArgumentException('The file could not be found or is empty.');
        }

        return $markup;
    }
    
    /**
     * Preprocesses the markup content
     * 
     * @param string $markup The raw markup content
     * @return array
     * @throws InvalidArgumentException If the markup structure is invalid
     */
    private function preprocessMarkup(string $markup): array
    {
        // Validate header count
        if (!str_contains($markup, '# ')) {
            throw new InvalidArgumentException(
                'This markup file contains no headers. It must have at least one # header. 
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
    private function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // Extract title from second section
        $this->title = $markupFormatted[1];
        $this->title = trim($this->title);
       
        //// Extract yaml from the first section
        $firstSection = explode('////', $markupFormatted[0]);
        unset($markupFormatted);
        
        // Make sure we have at least one part after the //// delimiter
        if (count($firstSection) < 2) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Either the content 
                or the metadata is missing.');
        }
        
        // Get the yaml tags
        if (!isset($firstSection[0])) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Missing YAML content.');
        }
        
        $yamlTags = create_tags_from_yaml($firstSection[0]);
        
        // Store optional tags
        $this->optionaltags = $yamlTags['tags'];
        unset($yamlTags);

        // Get the title tags
        if (!isset($firstSection[0])) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Missing a header/title content.');
        }
        
        $content = explode($firstSection[0], PHP_EOL);
        $this->title = $content[0];
        $this->content = $content[1];

        // Create d-tag
        $this->dTag = construct_d_tag_articles(
            $this->title
        );
        
        unset($firstSection);
        unset($content);

        echo PHP_EOL;
    }
    
    public function recordResult(string $kind, Event $note): void
    {
        // Get event ID with retry
        $eventID = $this->getEventIdWithRetry($note);
        
        // Log the event
        echo "Published " . $kind . " event with  ID " . $eventID . PHP_EOL . PHP_EOL;

        print_event_data(
            $kind, 
            $eventID, 
            $this->dTag);
        
        // Print a njump hyperlink
        echo "https://njump.me/" . $eventID . PHP_EOL;
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
    private function getEventIdWithRetry(
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
                'The wiki eventID was not created');
        }
        
        return $eventID;
    }

    /**
     * Builds a wiki event with the appropriate tags
     * 
     * @return Event The configured event
     */
    private function buildWikiEvent(): Event
    {
        // Add the UNIX time stamp, of when the article was first published.
        $this->optionaltags[] = ['published_at', time()];

        $tags[] = $this->dTag;
        $tags[] = $this->title;
        $tags[] = $this->optionaltags;
        
        $note = new Event();
        $note->setKind(self::EVENT_KIND);
        $note->setTags($tags);
        $note->setContent($this->content);
        
        return $note;
    }
}

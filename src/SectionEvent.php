<?php

use swentel\nostr\Event\Event;

/**
 * Class SectionEvent
 * 
 * Handles the creation and management of section events in the Nostr protocol.
 * Section events represent individual content sections within a publication,
 * each with its own metadata (title, author, version) and content.
 * These events are linked to the main publication event.
 * 
 * This class follows object-oriented principles with encapsulated properties
 * and clear method responsibilities.
 */
class SectionEvent
{
    // Section properties
    private string $sectionDTag = '';
    private string $sectionTitle = '';
    private string $sectionAuthor = '';
    private string $sectionVersion = '';
    private string $sectionContent = '';
    private array $sectionOptionalTags = [];
    
    // Constants
    public const EVENT_KIND = '30041';
    public const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';
    
    /**
     * Constructor for SectionEvent
     * 
     * @param array $data Optional initial data for the section
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            if (isset($data['title'])) {
                $this->setSectionTitle($data['title']);
            }
            
            if (isset($data['author'])) {
                $this->setSectionAuthor($data['author']);
            }
            
            if (isset($data['version'])) {
                $this->setSectionVersion($data['version']);
            }
            
            if (isset($data['content'])) {
                $this->setSectionContent($data['content']);
            }
            
            if (isset($data['dTag'])) {
                $this->setSectionDTag($data['dTag']);
            }
        }
    }
    
    /**
     * Get the section d-tag
     * 
     * @return string The section d-tag
     */
    public function getSectionDTag(): string
    {
        return $this->sectionDTag;
    }
    
    /**
     * Set the section d-tag
     * 
     * @param string $dTag The section d-tag
     * @return self
     */
    public function setSectionDTag(string $dTag): self
    {
        $this->sectionDTag = $dTag;
        return $this;
    }
    
    /**
     * Get the section title
     * 
     * @return string The section title
     */
    public function getSectionTitle(): string
    {
        return $this->sectionTitle;
    }
    
    /**
     * Set the section title
     * 
     * @param string $title The section title
     * @return self
     */
    public function setSectionTitle(string $title): self
    {
        $this->sectionTitle = $title;
        return $this;
    }
    
    /**
     * Get the section author
     * 
     * @return string The section author
     */
    public function getSectionAuthor(): string
    {
        return $this->sectionAuthor;
    }
    
    /**
     * Set the section author
     * 
     * @param string $author The section author
     * @return self
     */
    public function setSectionAuthor(string $author): self
    {
        $this->sectionAuthor = $author;
        return $this;
    }
    
    /**
     * Get the section version
     * 
     * @return string The section version
     */
    public function getSectionVersion(): string
    {
        return $this->sectionVersion;
    }
    
    /**
     * Set the section version
     * 
     * @param string $version The section version
     * @return self
     */
    public function setSectionVersion(string $version): self
    {
        $this->sectionVersion = $version;
        return $this;
    }
    
    /**
     * Get the section content
     * 
     * @return string The section content
     */
    public function getSectionContent(): string
    {
        return $this->sectionContent;
    }
    
    /**
     * Set the section content
     * 
     * @param string $content The section content
     * @return self
     */
    public function setSectionContent(string $content): self
    {
        $this->sectionContent = $content;
        return $this;
    }
    
    /**
     * Get the section optional tags
     * 
     * @return array The section optional tags
     */
    public function getSectionOptionalTags(): array
    {
        return $this->sectionOptionalTags;
    }
    
    /**
     * Set the section optional tags
     * 
     * @param array $tags The section optional tags
     * @return self
     */
    public function setSectionOptionalTags(array $tags): self
    {
        $this->sectionOptionalTags = $tags;
        return $this;
    }
    
    /**
     * Create a section event.
     * Returns the array containing the ID and d-tag for the section event.
     *
     * @return array The result containing eventID and dTag
     * @throws InvalidArgumentException If the section event ID was not created
     */
    public function createSection(): array
    {
        // Create and configure the event
        $sectionEvent = $this->buildSectionEvent();
        
        // Send the event
        prepare_event_data($sectionEvent);
        
        // Get event ID with retry
        $noteEventID = $this->getEventIdWithRetry($sectionEvent);
        
        // Log the event
        $this->logSectionEvent($noteEventID);
        
        // Return the result
        return [
            "eventID" => $noteEventID,
            "dTag" => $this->getSectionDTag()
        ];
    }
    
    /**
     * Builds a section event with the appropriate tags and content
     * 
     * @return Event The configured event
     */
    private function buildSectionEvent(): Event
    {
        $sectionEvent = new Event();
        
        $sectionEvent->setKind(self::EVENT_KIND);
        $sectionEvent->setContent($this->sectionContent);
        $sectionEvent->setTags(
            Tag::createSectionTags(
                $this->sectionDTag,
                $this->sectionTitle,
                $this->sectionAuthor,
                $this->sectionOptionalTags
            )
        );
        
        return $sectionEvent;
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
    private function getEventIdWithRetry(Event $note, int $maxRetries = 10, int $delay = 5): string
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
            throw new InvalidArgumentException('The section eventID was not created');
        }
        
        return $eventID;
    }
    
    /**
     * Logs a section event
     * 
     * @param string $eventID The event ID
     */
    private function logSectionEvent(string $eventID): void
    {
        echo PHP_EOL."Published " . self::EVENT_KIND . " event with ID " . $eventID . PHP_EOL;
        print_event_data(self::EVENT_KIND, $eventID, $this->getSectionDTag());
    }
}

<?php

use swentel\nostr\Event\Event;

/**
 * Class SectionEvent
 * 
 * Handles the creation and management of section events in the Nostr protocol.
 * Section events represent individual content sections within a publication,
 * each with its own metadata (title, author, version) and content.
 * These events are linked to the main publication event.
 */
class SectionEvent
{
    // Section properties
    public string $sectionDTag = '';
    public string $sectionTitle = '';
    public string $sectionAuthor = '';
    public string $sectionVersion = '';
    public string $sectionContent = '';
    public array $sectionOptionalTags = [];
    
    // Constants
    public const EVENT_KIND = '30041';
    
    /**
     * Constructor for SectionEvent
     * 
     * @param array $data Optional initial data for the section
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            if (isset($data['title'])) {
                $this->sectionTitle = $data['title'];
            }
            
            if (isset($data['author'])) {
                $this->sectionAuthor = $data['author'];
            }
            
            if (isset($data['version'])) {
                $this->sectionVersion = $data['version'];
            }
            
            if (isset($data['content'])) {
                $this->sectionContent = $data['content'];
            }
            
            if (isset($data['dTag'])) {
                $this->sectionDTag = $data['dTag'];
            }
        }
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
            "dTag" => $this->sectionDTag
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
        echo "Published " . self::EVENT_KIND . " event with ID " . $eventID . PHP_EOL;
        print_event_data(self::EVENT_KIND, $eventID, $this->sectionDTag);
    }
}

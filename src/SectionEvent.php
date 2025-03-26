<?php

use swentel\nostr\Event\Event;

include_once 'helperFunctions.php';
include_once 'PublicationEvent.php';

/**
 * Class SectionEvent
 * 
 * Handles the creation and management of section events in the Nostr protocol.
 */
class SectionEvent
{
    // Section properties
    private string $sectionDTag = '';
    private string $sectionTitle = '';
    private string $sectionAuthor = '';
    private string $sectionVersion = '';
    private string $sectionContent = '';
    
    // Constants
    private const EVENT_KIND = '30041';
    
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
     * Sets the section version
     * 
     * @param string $sectionVersion The version
     * @return self
     */
    public function setSectionVersion(string $sectionVersion): self
    {
        $this->sectionVersion = $sectionVersion;
        return $this;
    }
    
    /**
     * Gets the section version
     * 
     * @return string
     */
    public function getSectionVersion(): string
    {
        return $this->sectionVersion;
    }
    
    /**
     * Sets the section d-tag
     * 
     * @param string $sectionDTag The d-tag
     * @return self
     */
    public function setSectionDTag(string $sectionDTag): self
    {
        $this->sectionDTag = $sectionDTag;
        return $this;
    }
    
    /**
     * Gets the section d-tag
     * 
     * @return string
     */
    public function getSectionDTag(): string
    {
        return $this->sectionDTag;
    }
    
    /**
     * Sets the section title
     * 
     * @param string $sectionTitle The title
     * @return self
     */
    public function setSectionTitle(string $sectionTitle): self
    {
        $this->sectionTitle = $sectionTitle;
        return $this;
    }
    
    /**
     * Gets the section title
     * 
     * @return string
     */
    public function getSectionTitle(): string
    {
        return $this->sectionTitle;
    }
    
    /**
     * Sets the section author
     * 
     * @param string $sectionAuthor The author
     * @return self
     */
    public function setSectionAuthor(string $sectionAuthor): self
    {
        $this->sectionAuthor = $sectionAuthor;
        return $this;
    }
    
    /**
     * Gets the section author
     * 
     * @return string
     */
    public function getSectionAuthor(): string
    {
        return $this->sectionAuthor;
    }
    
    /**
     * Sets the section content
     * 
     * @param string $sectionContent The content
     * @return self
     */
    public function setSectionContent(string $sectionContent): self
    {
        $this->sectionContent = $sectionContent;
        return $this;
    }
    
    /**
     * Gets the section content
     * 
     * @return string
     */
    public function getSectionContent(): string
    {
        return $this->sectionContent;
    }
    
    /**
     * For backward compatibility
     */
    public function set_section_version($sectionVersion) {
        $this->setSectionVersion($sectionVersion);
    }
    
    public function get_section_version() {
        return $this->getSectionVersion();
    }
    
    public function set_section_d_tag($sectionDTag) {
        $this->setSectionDTag($sectionDTag);
    }
    
    public function get_section_d_tag() {
        return $this->getSectionDTag();
    }
    
    public function set_section_title($sectionTitle) {
        $this->setSectionTitle($sectionTitle);
    }
    
    public function get_section_title() {
        return $this->getSectionTitle();
    }
    
    public function set_section_author($sectionAuthor) {
        $this->setSectionAuthor($sectionAuthor);
    }
    
    public function get_section_author() {
        return $this->getSectionAuthor();
    }
    
    public function set_section_content($sectionContent) {
        $this->setSectionContent($sectionContent);
    }
    
    public function get_section_content() {
        return $this->getSectionContent();
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
        $note = $this->buildSectionEvent();
        
        // Send the event
        prepare_event_data($note);
        
        // Get event ID with retry
        $eventID = $this->getEventIdWithRetry($note);
        
        // Log the event
        $this->logSectionEvent($eventID);
        
        // Return the result
        return [
            "eventID" => $eventID,
            "dTag" => $this->getSectionDTag()
        ];
    }
    
    /**
     * For backward compatibility
     */
    public function create_section(): array
    {
        return $this->createSection();
    }
    
    /**
     * Builds a section event
     * 
     * @return Event The configured event
     */
    private function buildSectionEvent(): Event
    {
        $note = new Event();
        $note->setContent($this->getSectionContent());
        $note->setKind(self::EVENT_KIND);
        $note->setTags([
            ['d', $this->getSectionDTag()],
            ['title', $this->getSectionTitle()],
            ['author', $this->getSectionAuthor()],
            ["m", "text/asciidoc"],
            ["M", "article/publication-content/replaceable"],
        ]);
        
        return $note;
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
        print_event_data(self::EVENT_KIND, $eventID, $this->getSectionDTag());
    }

}

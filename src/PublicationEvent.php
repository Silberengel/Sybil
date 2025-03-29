<?php

use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;

/**
 * Class PublicationEvent
 * 
 * Handles the creation and publishing of publication events in the Nostr protocol.
 * This class processes AsciiDoc files, extracts metadata and content sections,
 * creates section events for each content section, and then creates a main
 * publication event that references all the section events. It supports both
 * a-tag and e-tag referencing methods.
 * Extends BaseEvent to leverage common event handling functionality.
 */
class PublicationEvent extends BaseEvent
{
    // Publication-specific properties
    protected string $author = '';
    protected string $version = '';
    protected string $tagType = '';
    protected string $autoUpdate = '';
    
    // Section tracking
    protected array $sectionEvents = [];
    protected array $sectionDtags = [];
    
    // Constants
    public const EVENT_KIND = '30040';
    public const SECTION_EVENT_KIND = '30041';
    
    /**
     * Constructor for PublicationEvent
     * 
     * @param array $data Optional initial data for the publication
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        
        if (!empty($data)) {
            if (isset($data['author'])) {
                $this->setAuthor($data['author']);
            }
            
            if (isset($data['version'])) {
                $this->setVersion($data['version']);
            }
        }
    }
    
    /**
     * Get the author
     * 
     * @return string The author
     */
    public function getAuthor(): string
    {
        return $this->author;
    }
    
    /**
     * Set the author
     * 
     * @param string $author The author
     * @return self
     */
    public function setAuthor(string $author): self
    {
        $this->author = $author;
        return $this;
    }
    
    /**
     * Get the version
     * 
     * @return string The version
     */
    public function getVersion(): string
    {
        return $this->version;
    }
    
    /**
     * Set the version
     * 
     * @param string $version The version
     * @return self
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }
    
    /**
     * Get the tag type
     * 
     * @return string The tag type
     */
    public function getTagType(): string
    {
        return $this->tagType;
    }
    
    /**
     * Set the tag type
     * 
     * @param string $tagType The tag type
     * @return self
     */
    public function setTagType(string $tagType): self
    {
        $this->tagType = $tagType;
        return $this;
    }
    
    /**
     * Get the auto update setting
     * 
     * @return string The auto update setting
     */
    public function getAutoUpdate(): string
    {
        return $this->autoUpdate;
    }
    
    /**
     * Set the auto update setting
     * 
     * @param string $autoUpdate The auto update setting
     * @return self
     */
    public function setAutoUpdate(string $autoUpdate): self
    {
        $this->autoUpdate = $autoUpdate;
        return $this;
    }
    
    /**
     * Get the section events
     * 
     * @return array The section events
     */
    public function getSectionEvents(): array
    {
        return $this->sectionEvents;
    }
    
    /**
     * Set the section events
     * 
     * @param array $sectionEvents The section events
     * @return self
     */
    public function setSectionEvents(array $sectionEvents): self
    {
        $this->sectionEvents = $sectionEvents;
        return $this;
    }
    
    /**
     * Add a section event
     * 
     * @param string $eventID The event ID
     * @return self
     */
    public function addSectionEvent(string $eventID): self
    {
        $this->sectionEvents[] = $eventID;
        return $this;
    }
    
    /**
     * Get the section d-tags
     * 
     * @return array The section d-tags
     */
    public function getSectionDtags(): array
    {
        return $this->sectionDtags;
    }
    
    /**
     * Set the section d-tags
     * 
     * @param array $sectionDtags The section d-tags
     * @return self
     */
    public function setSectionDtags(array $sectionDtags): self
    {
        $this->sectionDtags = $sectionDtags;
        return $this;
    }
    
    /**
     * Add a section d-tag
     * 
     * @param string $dTag The d-tag
     * @return self
     */
    public function addSectionDtag(string $dTag): self
    {
        $this->sectionDtags[] = $dTag;
        return $this;
    }

    /**
     * Create an index event and hang on the associated section events
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
        
        // Process sections
        $this->processSections($markupFormatted);
        
        // Create the publication event with appropriate tag type
        if ($this->tagType === 'a') {
            $this->createWithATags();
        } else {
            $this->createWithETags();
        }
    }
    
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
        return "publication";
    }
    
    /**
     * Loads and validates the markup file
     * 
     * @return string The markup content
     * @throws InvalidArgumentException If the file is invalid
     */
    protected function loadMarkupFile(): string
    {
        $markup = parent::loadMarkupFile();

        // Validate header levels
        if (stripos($markup, '======= ') !== false) {
            throw new InvalidArgumentException(
                'This markup file contains too many header levels. Please correct down to maximum six = levels and retry.'
            );
        }
        
        return $markup;
    }
    
    /**
     * Preprocesses the markup content
     * 
     * @param string $markup The raw markup content
     * @return array The processed markup sections
     * @throws InvalidArgumentException If the markup structure is invalid
     */
    protected function preprocessMarkup(string $markup): array
    {
        // Replace headers above == with &s for later processing
        $markup = $this->replaceHeadersForProcessing($markup);
        
        // Break the file into metadata and sections
        $markupFormatted = explode("== ", $markup);
        
        // Validate section count
        if (count($markupFormatted) === 1) {
            throw new InvalidArgumentException(
                'This markup file contains no = headers or only one level of headers. Please ensure there are two levels and retry.'
            );
        }
        
        return $markupFormatted;
    }
    
    /**
     * Replaces header markers for processing
     * 
     * @param string $markup The markup content
     * @return string The processed markup
     */
    private function replaceHeadersForProcessing(string $markup): string
    {
        $replacements = [
            '====== ' => '&&&&&& ',
            '===== ' => '&&&&& ',
            '==== ' => '&&&& ',
            '=== ' => '&&& '
        ];
        
        return str_replace(
            array_keys($replacements), 
            array_values($replacements), 
            $markup);
    }
    
    /**
     * Restores header markers after processing
     * 
     * @param array $markupFormatted The markup sections
     * @return array The processed markup sections
     */
    private function restoreHeaderMarkers(
        array $markupFormatted): array
    {
        $replacements = [
            '&&&&&& ' => "[discrete]\n====== ",
            '&&&&& ' => "[discrete]\n===== ",
            '&&&& ' => "[discrete]\n==== ",
            '&&& ' => "[discrete]\n=== "
        ];
        
        $result = [];
        foreach ($markupFormatted as $section) {
            $result[] = str_replace(
                array_keys($replacements), 
                array_values($replacements), 
                $section);
        }
        
        return $result;
    }
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // Extract title from first section
        $firstSection = explode(PHP_EOL, 
        $markupFormatted[0], 2);
        $this->title = trim(
            trim($firstSection[0], "= "));
        
        $firstSectionNew = explode('////', $firstSection[1]);
        unset($firstSection);
        
        // Make sure we have at least one part after the //// delimiter
        if (count($firstSectionNew) < 2) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Missing YAML section.');
        }
        
        array_shift($firstSectionNew);

        // Process preamble if it exists
        $preamble = isset($firstSectionNew[1]) ? trim($firstSectionNew[1]) : '';
        if (!empty($preamble)) {
            $markupFormatted[0] = 'Preamble' . PHP_EOL . PHP_EOL . $preamble;
        } else {
            unset($markupFormatted[0]);
        }
        unset($preamble);

        // Get the yaml tags
        if (!isset($firstSectionNew[0])) {
            throw new InvalidArgumentException(
                'The file format is incorrect. Missing YAML content.');
        }
        
        $yamlTags = create_tags_from_yaml($firstSectionNew[0]);
        unset($firstSectionNew);

        // Set publication properties from YAML data
        if (empty($yamlTags['author'])) {
            throw new InvalidArgumentException(
                'The author is missing.');
        }
        $this->setAuthor($yamlTags['author']);
        
        if (empty($yamlTags['version'])) {
            throw new InvalidArgumentException(
                'The version is missing.');
        }
        $this->setVersion($yamlTags['version']);
        
        if ($yamlTags['tag-type'] !== 'e' && $yamlTags['tag-type'] !== 'a') {
            throw new InvalidArgumentException(
                'The event type (e/a) is missing or wrong.');
        }
        $this->setTagType($yamlTags['tag-type']);
        
        if ($yamlTags['auto-update'] !== 'yes' && $yamlTags['auto-update'] !== 'ask' && $yamlTags['auto-update'] !== 'no') {
            throw new InvalidArgumentException(
                'The auto-update option is missing or wrong.');
        }
        $this->setAutoUpdate($yamlTags['auto-update']);
        
        // Store optional tags
        $this->setOptionalTags($yamlTags['tags']);

        // Create d-tag
        $this->dTag = construct_d_tag_publication(
            $this->title,
            $this->author,
            $this->version
        );
        
        unset($yamlTags);

        echo PHP_EOL;
    }
    
    /**
     * Processes sections and creates section events
     * 
     * @param array $markupFormatted The markup sections
     */
    private function processSections(array $markupFormatted): void
    {
        // Restore header markers
        $markupFormatted = $this->restoreHeaderMarkers(
            $markupFormatted);
        $sectionCount = count($markupFormatted);

        // Process each section
        $sectionNum = 0;
        foreach ($markupFormatted as $section) {
            $sectionNum++;
            echo PHP_EOL.'Building section '.$sectionNum.' of '
            .$sectionCount.'.'.PHP_EOL;
            $this->processSection(
                $section, 
                $sectionNum);
        }
    }
    
    /**
     * Processes a single section
     * 
     * @param string $section The section content
     * @param int $sectionNum The section number
     */
    private function processSection(string $section, int $sectionNum): void
    {
        $sectionParts = explode('////', $section);

        // Get the yaml tags
        $yamlTags = null;
        if(isset($sectionParts[1]) && !empty($sectionParts[1])){
            if (str_contains($sectionParts[1], '<<YAML>>')) {
                $yamlContent = trim($sectionParts[1]);
                $yamlTags = create_tags_from_yaml($yamlContent);
            }
        }
        
        // Create section event
        $nextSection = new SectionEvent();
        
        if($yamlTags){
            // Set author from section YAML if available, 
            // otherwise use publication author
            if (!isset($yamlTags['author']) || empty($yamlTags['author'])) {
                $nextSection->setSectionAuthor($this->author);
            } else{
                $nextSection->setSectionAuthor($yamlTags['author']);
            }
            if (!isset($yamlTags['version']) || empty($yamlTags['version'])) {
                $nextSection->setSectionVersion($this->version);
            } else{
                $nextSection->setSectionVersion($yamlTags['version']);
            }
            if (!isset($yamlTags['title']) || empty($yamlTags['title'])) {
                $nextSection->setSectionTitle(trim(
                    strstr($sectionParts[0], 
                    "\n", true)));
            } else{
                $nextSection->setSectionTitle($yamlTags['title']);
            }      
        } else {
            $nextSection->setSectionAuthor($this->author);
            $nextSection->setSectionVersion($this->version);
            $nextSection->setSectionTitle(trim(
                strstr(
                    $sectionParts[0], 
                    "\n", 
                    true)));
        }
        
        // Create section d-tag
        $sectionTitle = $nextSection->getSectionTitle();
        $sectionAuthor = $nextSection->getSectionAuthor();
        $sectionVersion = $nextSection->getSectionVersion();
        
        $nextSection->setSectionDTag(construct_d_tag_publication(
            $this->title . "-" . $sectionTitle 
            . "-" . $sectionNum,
            $sectionAuthor,
            $sectionVersion
        ));
        
        // Set section content
        if($yamlTags){
            $nextSection->setSectionContent(
                trim(trim(
                        trim(
                            strval($section), 
                            $sectionTitle
                            ), 
                            $sectionParts[0]
                    )
                )
            );
        } else{
            $nextSection->setSectionContent(
                trim(trim(
                    strval($section), 
                    $sectionTitle
                    )
                )
            );
        }
        
        // Create section and store results
        $sectionData = $nextSection->createSection();
        $this->addSectionEvent($sectionData["eventID"]);
        $this->addSectionDtag($sectionData["dTag"]);
    }
    
    /**
     * Creates a publication event with a-tags
     * 
     * @return void
     */
    public function createWithATags(): void
    {
        // Get public hex key
        $keys = new Key();
        $privateBech32 = getenv('NOSTR_SECRET_KEY');
        $privateHex = $keys->convertToHex(key: $privateBech32);
        $publicHex = $keys->getPublicKey(private_hex: $privateHex);
        
        // Build base tags
        $publicationEvent = $this->buildPublicationEvent(
            'a',
            $publicHex);
        
        prepare_event_data($publicationEvent);
        $this->recordResultWithTagType(
            self::EVENT_KIND, 
            $publicationEvent,
            $this->tagType);
    }
    
    /**
     * Creates a publication event with e-tags
     * 
     * @return void
     */
    public function createWithETags(): void
    {
        // Build base tags
        $event = $this->buildPublicationEvent('e');
        
        // Create and send the event
        prepare_event_data($event);
        $this->recordResultWithTagType(
            self::EVENT_KIND, 
            $event, 
            $this->tagType);
    }
    
    /**
     * Records the result of creating an event with tag type
     * 
     * @param string $kind The event kind
     * @param Event $note The event
     * @param string $type The tag type
     * @return void
     * @throws InvalidArgumentException If the event ID was not created
     */
    public function recordResultWithTagType(string $kind, Event $note, string $type): void
    {
        // Get event ID with retry
        $eventID = $this->getEventIdWithRetry($note);
        
        // Log the event
        echo PHP_EOL."Published " . $kind . " event with " 
        . $type . " tags and ID " . $eventID . PHP_EOL . PHP_EOL;

        print_event_data(
            $kind, 
            $eventID, 
            $this->getDTag());
        
        // Print a njump hyperlink
        echo "https://njump.me/" . $eventID . PHP_EOL;
    }

    /**
     * Builds an event with the appropriate tags
     * 
     * @return Event The configured event
     */
    protected function buildEvent(): Event
    {
        // This is a placeholder - publication events are created through createWithATags or createWithETags
        throw new \LogicException("Publication events should be created through createWithATags or createWithETags");
    }
    
    /**
     * Builds a publication event with the appropriate tags
     * 
     * @param string $type The tag type ('a' or 'e')
     * @param string $publicHex The public hex key (required for a-tags)
     * @return Event The configured event
     */
    protected function buildPublicationEvent(
        string $type = 'a', $publicHex = ""): Event
    {

        if($type === 'a'){
            $eventTags = Tag::addATags(
                $this->getSectionEvents(),
                $this->getSectionDtags(),
                self::SECTION_EVENT_KIND,
                $publicHex,
                self::DEFAULT_RELAY
            );
        }else {
            $eventTags = Tag::addETags(
                $this->getSectionEvents()
            );
        }

        // Merge with optional tags
        $eventTags = array_merge($eventTags, $this->getOptionalTags());
        
        $note = new Event();
        
        $note->setKind(self::EVENT_KIND);
        $note->setTags(
            Tag::createPublicationTags(
                $this->getDTag(),
                $this->getTitle(),
                $this->getAuthor(),
                $this->getVersion(),
                $eventTags
            )
        );
        $note->setContent("");
        
        return $note;
    }
}

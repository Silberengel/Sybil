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
 */
class PublicationEvent
{
    // Publication properties
    public string $file = '';
    public string $dTag = '';
    public string $title = '';
    public string $author = '';
    public string $version = '';
    public string $tagType = '';
    public string $autoUpdate = '';
    public array $optionaltags = [];
    
    // Section tracking
    public array $sectionEvents = [];
    public array $sectionDtags = [];
    
    // Constants
    public const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';
    public const EVENT_KIND = '30040';
    public const SECTION_EVENT_KIND = '30041';
    
/**
 * Constructor for PublicationEvent
 * 
 * @param array $data Optional initial data for the publication
 */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            if (isset($data['title'])) {
                $this->title = $data['title'];
            }
            
            if (isset($data['author'])) {
                $this->author = $data['author'];
            }
            
            if (isset($data['version'])) {
                $this->version = $data['version'];
            }
            
            if (isset($data['dTag'])) {
                $this->dTag = $data['dTag'];
            }
        }
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
    private function preprocessMarkup(string $markup): array
    {
        // Replace headers above == with &s for later processing
        $markup = $this->replaceHeadersForProcessing($markup);
        
        // Break the file into metadata and sections
        $markupFormatted = explode("== ", $markup);
        
        // Validate section count
        if (count($markupFormatted) === 1) {
            throw new InvalidArgumentException(
                'This markup file contains no headers or only one level of headers. Please ensure there are two levels and retry.'
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
    private function extractTitleAndCreateDTag(array &$markupFormatted): void
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
        $this->author = $yamlTags['author'];
        
        if (empty($yamlTags['version'])) {
            throw new InvalidArgumentException(
                'The version is missing.');
        }
        $this->version = $yamlTags['version'];
        
        if ($yamlTags['tag-type'] !== 'e' && $yamlTags['tag-type'] !== 'a') {
            throw new InvalidArgumentException(
                'The event type (e/a) is missing or wrong.');
        }
        $this->tagType = $yamlTags['tag-type'];
        
        if ($yamlTags['auto-update'] !== 'yes' && $yamlTags['auto-update'] !== 'ask' && $yamlTags['auto-update'] !== 'no') {
            throw new InvalidArgumentException(
                'The auto-update option is missing or wrong.');
        }
        $this->autoUpdate = $yamlTags['auto-update'];
        
        // Store optional tags
        $this->optionaltags = $yamlTags['tags'];

        // Create d-tag
        $this->dTag = construct_d_tag(
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
                $nextSection->sectionAuthor = $this->author;
            } else{
                $nextSection->sectionAuthor = $yamlTags['author'];
            }
            if (!isset($yamlTags['version']) || empty($yamlTags['version'])) {
                $nextSection->sectionVersion = $this->version;
            } else{
                $nextSection->sectionVersion = $yamlTags['version'];
            }
            if (!isset($yamlTags['title']) || empty($yamlTags['title'])) {
                $nextSection->sectionTitle = trim(
                    strstr($sectionParts[0], 
                    "\n", true));
            } else{
                $nextSection->sectionTitle = $yamlTags['title'];
            }      
        } else {
            $nextSection->sectionAuthor = $this->author;
            $nextSection->sectionVersion = $this->version;
            $nextSection->sectionTitle = trim(
                strstr(
                    $sectionParts[0], 
                    "\n", 
                    true));
        }
        
        // Create section d-tag
        $nextSection->sectionDTag = construct_d_tag(
            $this->title . "-" . $nextSection->sectionTitle 
            . "-" . $sectionNum,
            $nextSection->sectionAuthor,
            $nextSection->sectionVersion
        );
        
        // Set section content

        if($yamlTags){
            $nextSection->sectionContent = (
                trim(trim(
                        trim(
                            strval($section), 
                            $nextSection->sectionTitle
                            ), 
                            $sectionParts[0]
                    )
                )
            );
        } else{
            $nextSection->sectionContent = (
                trim(trim(
                    strval($section), 
                    $nextSection->sectionTitle
                    )
                )
            );
        }
        
        // Create section and store results
        $sectionData = $nextSection->createSection();
        $this->sectionEvents[] = $sectionData["eventID"];
        $this->sectionDtags[] = $sectionData["dTag"];
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
        $this->recordResult(
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
        $this->recordResult(
            self::EVENT_KIND, 
            $event, 
            $this->tagType);
    }
    
    /**
     * Records the result of creating an event
     * 
     * @param string $kind The event kind
     * @param Event $note The event
     * @param string $type The tag type
     * @return void
     * @throws InvalidArgumentException If the event ID was not created
     */
    public function recordResult(string $kind, Event $note, string $type): void
    {
        // Get event ID with retry
        $eventID = $this->getEventIdWithRetry($note);
        
        // Log the event
        echo "Published " . $kind . " event with " 
        . $type . " tags and ID " . $eventID . PHP_EOL . PHP_EOL;

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
                'The publication eventID was not created');
        }
        
        return $eventID;
    }

    /**
     * Builds a publication event with the appropriate tags
     * 
     * @param string $type The tag type ('a' or 'e')
     * @param string $publicHex The public hex key (required for a-tags)
     * @return Event The configured event
     */
    private function buildPublicationEvent(
        string $type = 'a', $publicHex = ""): Event
    {

        if($type === 'a'){
            $eventTags = Tag::addATags(
                $this->sectionEvents,
                $this->sectionDtags,
                self::SECTION_EVENT_KIND,
                $publicHex,
                self::DEFAULT_RELAY
            );
        }else {
            $eventTags = Tag::addETags(
                $this->sectionEvents
            );
        }

        // Merge with optional tags
        $eventTags = array_merge($eventTags, $this->optionaltags);
        
        $note = new Event();
        
        $note->setKind(self::EVENT_KIND);
        $note->setTags(
            Tag::createPublicationTags(
                $this->dTag,
                $this->title,
                $this->author,
                $this->version,
                $eventTags
            )
        );
        $note->setContent("");
        
        return $note;
    }
}

<?php

use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
include_once 'helperFunctions.php';
include_once 'SectionEvent.php';

/**
 * Class PublicationEvent
 * 
 * Handles the creation and publishing of publication events in the Nostr protocol.
 */
class PublicationEvent
{
    // Publication properties
    private array $publicationSettings = [];
    private string $publicationDTag = '';
    private string $publicationTitle = '';
    private string $publicationAuthor = '';
    private string $publicationVersion = '';
    private string $publicationTagType = '';
    private bool $publicationAutoUpdate = false;
    private array $publicationFileTags = [];
    
    // Section tracking
    private array $sectionEvents = [];
    private array $sectionDtags = [];
    
    // Constants
    private const DEFAULT_RELAY = 'wss://thecitadel.nostr1.com';
    private const EVENT_KIND = '30040';
    private const SECTION_EVENT_KIND = '30041';
    
    /**
     * Constructor for PublicationEvent
     * 
     * @param array $settings Optional initial settings for the publication
     */
    public function __construct(array $settings = [])
    {
        if (!empty($settings)) {
            $this->setPublicationSettings($settings);
        }
    }
    
    /**
     * Sets the publication settings
     * 
     * @param array $publicationSettings The settings array
     * @return self
     */
    public function setPublicationSettings(array $publicationSettings): self
    {
        $this->publicationSettings = $publicationSettings;
        
        // Initialize properties from settings if available
        if (isset($publicationSettings['author'])) {
            $this->setPublicationAuthor($publicationSettings['author']);
        }
        
        if (isset($publicationSettings['version'])) {
            $this->setPublicationVersion($publicationSettings['version']);
        }
        
        if (isset($publicationSettings['tag-type'])) {
            $this->setPublicationTagtype($publicationSettings['tag-type']);
        }
        
        if (isset($publicationSettings['auto-update'])) {
            $this->setPublicationAutoupdate($publicationSettings['auto-update']);
        }
        
        if (isset($publicationSettings['tags'])) {
            $this->setPublicationFiletags($publicationSettings['tags']);
        }
        
        return $this;
    }
    
    /**
     * Gets the publication settings
     * 
     * @return array
     */
    public function getPublicationSettings(): array
    {
        return $this->publicationSettings;
    }
    
    /**
     * Sets the publication d-tag
     * 
     * @param string $publicationDTag The d-tag
     * @return self
     */
    public function setPublicationDTag(string $publicationDTag): self
    {
        $this->publicationDTag = $publicationDTag;
        return $this;
    }
    
    /**
     * Gets the publication d-tag
     * 
     * @return string
     */
    public function getPublicationDTag(): string
    {
        return $this->publicationDTag;
    }
    
    /**
     * Sets the publication title
     * 
     * @param string $publicationTitle The title
     * @return self
     */
    public function setPublicationTitle(string $publicationTitle): self
    {
        $this->publicationTitle = $publicationTitle;
        return $this;
    }
    
    /**
     * Gets the publication title
     * 
     * @return string
     */
    public function getPublicationTitle(): string
    {
        return $this->publicationTitle;
    }
    
    /**
     * Sets the publication author
     * 
     * @param string $publicationAuthor The author
     * @return self
     */
    public function setPublicationAuthor(string $publicationAuthor): self
    {
        $this->publicationAuthor = $publicationAuthor;
        return $this;
    }
    
    /**
     * Gets the publication author
     * 
     * @return string
     */
    public function getPublicationAuthor(): string
    {
        return $this->publicationAuthor;
    }
    
    /**
     * Sets the publication version
     * 
     * @param string $publicationVersion The version
     * @return self
     */
    public function setPublicationVersion(string $publicationVersion): self
    {
        $this->publicationVersion = $publicationVersion;
        return $this;
    }
    
    /**
     * Gets the publication version
     * 
     * @return string
     */
    public function getPublicationVersion(): string
    {
        return $this->publicationVersion;
    }
    
    /**
     * Sets the publication tag type
     * 
     * @param string $publicationTagType The tag type ('a' or 'e')
     * @return self
     */
    public function setPublicationTagtype(string $publicationTagType): self
    {
        $this->publicationTagType = $publicationTagType;
        return $this;
    }
    
    /**
     * Gets the publication tag type
     * 
     * @return string
     */
    public function getPublicationTagtype(): string
    {
        return $this->publicationTagType;
    }
    
    /**
     * Sets the publication auto-update flag
     * 
     * @param bool $publicationAutoUpdate The auto-update flag
     * @return self
     */
    public function setPublicationAutoupdate(bool $publicationAutoUpdate): self
    {
        $this->publicationAutoUpdate = $publicationAutoUpdate;
        return $this;
    }
    
    /**
     * Gets the publication auto-update flag
     * 
     * @return bool
     */
    public function getPublicationAutoupdate(): bool
    {
        return $this->publicationAutoUpdate;
    }
    
    /**
     * Sets the publication file tags
     * 
     * @param array $publicationFileTags The file tags
     * @return self
     */
    public function setPublicationFiletags(array $publicationFileTags): self
    {
        $this->publicationFileTags = $publicationFileTags;
        return $this;
    }
    
    /**
     * Gets the publication file tags
     * 
     * @return array
     */
    public function getPublicationFiletags(): array
    {
        return $this->publicationFileTags;
    }
    
    /**
     * Adds a section event ID
     * 
     * @param string $sectionEvent The section event ID
     * @return self
     */
    public function addSectionEvent(string $sectionEvent): self
    {
        $this->sectionEvents[] = $sectionEvent;
        return $this;
    }
    
    /**
     * Gets all section events
     * 
     * @return array
     */
    public function getSectionEvents(): array
    {
        return $this->sectionEvents;
    }
    
    /**
     * Adds a section d-tag
     * 
     * @param string $sectionDtag The section d-tag
     * @return self
     */
    public function addSectionDTag(string $sectionDtag): self
    {
        $this->sectionDtags[] = $sectionDtag;
        return $this;
    }
    
    /**
     * Gets all section d-tags
     * 
     * @return array
     */
    public function getSectionDTags(): array
    {
        return $this->sectionDtags;
    }

    /**
     * Create an index event and hang on the associated section events
     * 
     * @return void
     * @throws InvalidArgumentException If the file is invalid or has formatting issues
     */
    public function publish_publication(): void
    {
        $this->publishPublication();
    }

    /**
     * Create an index event and hang on the associated section events
     * 
     * @return void
     * @throws InvalidArgumentException If the file is invalid or has formatting issues
     */
    public function publishPublication(): void
    {
        // Load and validate the markdown file
        $markdown = $this->loadMarkdownFile();
        
        // Process the markdown content
        $markdownFormatted = $this->preprocessMarkdown($markdown);
        
        // Extract title and create d-tag
        $this->extractTitleAndCreateDTag($markdownFormatted);
        
        // Process sections
        $this->processSections($markdownFormatted);
        
        // Create the publication event with appropriate tag type
        if ($this->getPublicationTagtype() === 'e') {
            $this->createPublicationWithETags();
        } else {
            $this->createPublicationWithATags();
        }
    }
    
    /**
     * Loads and validates the markdown file
     * 
     * @return string The markdown content
     * @throws InvalidArgumentException If the file is invalid
     */
    private function loadMarkdownFile(): string
    {
        if (!isset($this->publicationSettings['file'])) {
            throw new InvalidArgumentException('No file specified in publication settings.');
        }
        
        $markdown = file_get_contents($this->publicationSettings['file']);
        if (!$markdown) {
            throw new InvalidArgumentException('The file could not be found or is empty.');
        }
        
        // Validate header levels
        if (stripos($markdown, '======= ') !== false) {
            throw new InvalidArgumentException(
                'This markdown file contains too many header levels. Please correct down to maximum six = levels and retry.'
            );
        }
        
        return $markdown;
    }
    
    /**
     * Preprocesses the markdown content
     * 
     * @param string $markdown The raw markdown content
     * @return array The processed markdown sections
     * @throws InvalidArgumentException If the markdown structure is invalid
     */
    private function preprocessMarkdown(string $markdown): array
    {
        // Replace headers above == with &s for later processing
        $markdown = $this->replaceHeadersForProcessing($markdown);
        
        // Break the file into metadata and sections
        $markdownFormatted = explode("== ", $markdown);
        
        // Validate section count
        if (count($markdownFormatted) === 1) {
            throw new InvalidArgumentException(
                'This markdown file contains no headers or only one level of headers. Please ensure there are two levels and retry.'
            );
        }
        
        return $markdownFormatted;
    }
    
    /**
     * Replaces header markers for processing
     * 
     * @param string $markdown The markdown content
     * @return string The processed markdown
     */
    private function replaceHeadersForProcessing(string $markdown): string
    {
        $replacements = [
            '====== ' => '&&&&&& ',
            '===== ' => '&&&&& ',
            '==== ' => '&&&& ',
            '=== ' => '&&& '
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $markdown);
    }
    
    /**
     * Restores header markers after processing
     * 
     * @param array $markdownFormatted The markdown sections
     * @return array The processed markdown sections
     */
    private function restoreHeaderMarkers(array $markdownFormatted): array
    {
        $replacements = [
            '&&&&&& ' => "[discrete]\n====== ",
            '&&&&& ' => "[discrete]\n===== ",
            '&&&& ' => "[discrete]\n==== ",
            '&&& ' => "[discrete]\n=== "
        ];
        
        $result = [];
        foreach ($markdownFormatted as $section) {
            $result[] = str_replace(array_keys($replacements), array_values($replacements), $section);
        }
        
        return $result;
    }
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markdownFormatted The markdown sections (modified in place)
     */
    private function extractTitleAndCreateDTag(array &$markdownFormatted): void
    {
        // Extract title from first section
        $firstSection = explode(PHP_EOL, $markdownFormatted[0], 2);
        $this->setPublicationTitle(trim(trim($firstSection[0], "= ")));
        
        // Process preamble
        $markdownFormatted[0] = trim($firstSection[1]);
        
        if (!empty($markdownFormatted[0])) {
            $markdownFormatted[0] = 'Preamble' . PHP_EOL . PHP_EOL . $markdownFormatted[0];
        } else {
            unset($markdownFormatted[0]);
        }
        
        // Create d-tag
        $dTag = construct_d_tag(
            $this->getPublicationTitle(),
            $this->getPublicationAuthor(),
            $this->getPublicationVersion()
        );
        $this->setPublicationDTag($dTag);
        
        echo PHP_EOL;
    }
    
    /**
     * Processes sections and creates section events
     * 
     * @param array $markdownFormatted The markdown sections
     */
    private function processSections(array $markdownFormatted): void
    {
        // Restore header markers
        $markdownFormatted = $this->restoreHeaderMarkers($markdownFormatted);
        
        // Process each section
        $sectionNum = 0;
        foreach ($markdownFormatted as $section) {
            $sectionNum++;
            $this->processSection($section, $sectionNum);
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
        // Count down the publication of all sections
        $sectionCount = count($this->getSectionEvents());
        echo PHP_EOL.'Building section '.$sectionNum.' of '.$sectionCount.'.'.PHP_EOL;
        
        // Extract section title
        $sectionTitle = trim(strstr($section, "\n", true));
        
        // Create section event
        $nextSection = new SectionEvent();
        $nextSection->set_section_author($this->publicationAuthor);
        $nextSection->set_section_version($this->publicationVersion);
        $nextSection->set_section_title($sectionTitle);
        
        // Create section d-tag
        $sectionDTag = construct_d_tag(
            $this->getPublicationTitle() . "-" . $sectionTitle . "-" . $sectionNum,
            $nextSection->get_section_author(),
            $nextSection->get_section_version()
        );
        $nextSection->set_section_d_tag($sectionDTag);
        
        // Set section content
        $nextSection->set_section_content(trim(trim(strval($section), $sectionTitle)));
        
        // Create section and store results
        $sectionData = $nextSection->create_section();
        $this->addSectionEvent($sectionData["eventID"]);
        $this->addSectionDTag($sectionData["dTag"]);
    }

    /**
     * Creates a publication event with a-tags
     * 
     * @return void
     */
    public function createPublicationWithATags(): void
    {
        // Get public hex key
        $keys = new Key();
        $privateBech32 = getenv('NOSTR_SECRET_KEY');
        $privateHex = $keys->convertToHex(key: $privateBech32);
        $publicHex = $keys->getPublicKey(private_hex: $privateHex);
        
        // Build base tags
        $tags = $this->buildTags();
        
        // Add section references with a-tags
        foreach ($this->getSectionEvents() as $eventID) {
            $dTag = array_shift($this->sectionDtags);
            $tags[] = [
                'a', 
                self::SECTION_EVENT_KIND . ':' . $publicHex . ':' . $dTag, 
                self::DEFAULT_RELAY, 
                $eventID
            ];
        }
        
        // Create and send the event
        $this->createAndSendEvent($tags, 'a');
    }
    
    /**
     * Creates a publication event with e-tags
     * 
     * @return void
     */
    public function createPublicationWithETags(): void
    {
        // Build base tags
        $tags = $this->buildTags();
        
        // Add section references with e-tags
        foreach ($this->getSectionEvents() as $eventID) {
            $tags[] = ['e', $eventID];
        }
        
        // Create and send the event
        $this->createAndSendEvent($tags, 'e');
    }
    
    /**
     * Builds the base tags for the publication event
     * 
     * @return array The base tags
     */
    public function buildTags(): array
    {
        $tags = $this->getPublicationFiletags();
        $tags[] = ['d', $this->getPublicationDTag()];
        $tags[] = ['title', $this->getPublicationTitle()];
        $tags[] = ['author', $this->getPublicationAuthor()];
        $tags[] = ['version', $this->getPublicationVersion()];
        $tags[] = ['m', 'application/json'];
        $tags[] = ['M', 'meta-data/index/replaceable'];
        
        return $tags;
    }
    
    /**
     * Creates and sends an event with the given tags
     * 
     * @param array $tags The event tags
     * @param string $type The tag type ('a' or 'e')
     * @return void
     */
    private function createAndSendEvent(array $tags, string $type): void
    {
        $note = new Event();
        $note->setKind(self::EVENT_KIND);
        $note->setTags($tags);
        $note->setContent('');
        
        prepare_event_data($note);
        $this->recordResult(self::EVENT_KIND, $note, $type);
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
        echo "Published " . $kind . " event with " . $type . " tags and ID " . $eventID . PHP_EOL . PHP_EOL;
        print_event_data($kind, $eventID, $this->getPublicationDTag());
        
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
            throw new InvalidArgumentException('The publication eventID was not created');
        }
        
        return $eventID;
    }
}

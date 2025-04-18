<?php

namespace Sybil\Service;

/**
 * Service for managing tags
 * 
 * This service handles tag-related functionality, such as creating and
 * formatting d-tags and creating event tags.
 */
class TagService
{
    /**
     * @var EventService Event service
     */
    private EventService $eventService;
    
    /**
     * Constructor
     *
     * @param EventService $eventService Event service
     */
    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }
    
    /**
     * Construct a d-tag for a publication
     *
     * @param string $title The title of the publication
     * @param string $author The author name (optional, defaults to "unknown")
     * @param string $version The version number (optional)
     * @return string The formatted d-tag
     */
    public function constructDTagPublication(string $title, string $author = "unknown", string $version = "1"): string
    {
        // Replace spaces with dashes
        $normalizedTitle = $this->normalizeTagComponent($title);
        $normalizedAuthor = $this->normalizeTagComponent($author);
        
        // Construct the base d-tag
        $dTag = $normalizedTitle . "-by-" . $normalizedAuthor;
        
        // Add version if provided
        if (!empty($version)) {
            $normalizedVersion = $this->normalizeTagComponent($version);
            $dTag .= "-v-" . $normalizedVersion;
        }
        
        // Final formatting: lowercase and remove punctuation except and hyphens
        return $this->formatDTag($dTag);
    }
    
    /**
     * Construct a d-tag for articles
     *
     * @param string $title The title of the article
     * @return string The formatted d-tag
     */
    public function constructDTagArticles(string $title): string
    {
        $publicHex = get_public_hex_key();
        
        // Replace spaces with dashes
        $normalizedTitle = $this->normalizeTagComponent($title);
        
        // Construct the base d-tag
        $dTag = $normalizedTitle . "-by-" . substr($publicHex, 10);
        
        // Final formatting: lowercase and remove punctuation except hyphens
        return $this->formatDTag($dTag);
    }
    
    /**
     * Normalize a component for use in a d-tag
     *
     * @param string $component The component to normalize
     * @return string The normalized component
     */
    public function normalizeTagComponent(string $component): string
    {
        return strval(preg_replace('/\s+/', '-', $component));
    }
    
    /**
     * Format a d-tag according to the required rules
     *
     * @param string $dTag The raw d-tag to format
     * @return string The formatted d-tag
     */
    public function formatDTag(string $dTag): string
    {
        // Convert to UTF-8, lowercase, and remove all punctuation except periods and hyphens
        return substr(
            strtolower(
                preg_replace(
                    "/(?![.-])\p{P}/u", 
                    "", 
                    mb_convert_encoding($dTag, 'UTF-8', mb_list_encodings())
                )
            ), 
            0, 
            75
        );
    }
    
    /**
     * Create publication tags
     *
     * @param string $dTag The d-tag
     * @param string $title The title
     * @param string $author The author
     * @param string $version The version
     * @param array $additionalTags Additional tags to include
     * @return array The publication tags
     */
    public function createPublicationTags(string $dTag, string $title, string $author, string $version, array $additionalTags = []): array
    {
        $tags = [
            ['d', $dTag],
            ['title', $title],
            ['published_at', time()],
            ['author', $author],
            ['version', $version]
        ];
        
        return array_merge($tags, $additionalTags);
    }
    
    /**
     * Create section tags
     *
     * @param string $dTag The d-tag
     * @param string $title The title
     * @param string $author The author
     * @param array $additionalTags Additional tags to include
     * @return array The section tags
     */
    public function createSectionTags(string $dTag, string $title, string $author, array $additionalTags = []): array
    {
        $tags = [
            ['d', $dTag],
            ['title', $title],
            ['published_at', time()],
            ['author', $author]
        ];
        
        return array_merge($tags, $additionalTags);
    }
    
    /**
     * Add a-tags to an event
     *
     * @param array $eventIds The event IDs
     * @param array $dTags The d-tags
     * @param string $kind The event kind
     * @param string $publicHex The public hex key
     * @param string $relay The relay URL
     * @return array The a-tags
     */
    public function addATags(array $eventIds, array $dTags, string $kind, string $publicHex, string $relay): array
    {
        $tags = [];
        
        for ($i = 0; $i < count($eventIds); $i++) {
            $tags[] = ['a', $dTags[$i] . ':' . $publicHex . ':' . $kind, $relay, 'wss'];
        }
        
        return $tags;
    }
    
    /**
     * Add e-tags to an event
     *
     * @param array $eventIds The event IDs
     * @return array The e-tags
     */
    public function addETags(array $eventIds): array
    {
        $tags = [];
        
        foreach ($eventIds as $eventId) {
            $tags[] = ['e', $eventId];
        }
        
        return $tags;
    }
    
    /**
     * Create tags from YAML data
     *
     * @param string $yamlSnippet The YAML snippet
     * @return array The tags
     */
    public function createTagsFromYaml(string $yamlSnippet): array
    {
        // Initialize result array
        $result = [
            'title' => '',
            'author' => '',
            'version' => '',
            'tag-type' => '',
            'auto-update' => '',
            'tags' => []
        ];
        
        // Extract YAML content
        $yamlSnippet = trim($yamlSnippet);
        $yamlSnippet = ltrim($yamlSnippet, '<<YAML>>');
        $yamlSnippet = rtrim($yamlSnippet, '<</YAML>>');
        $yamlSnippet = trim($yamlSnippet);
        
        $parsedYaml = yaml_parse($yamlSnippet);
        
        // Check if parsing was successful
        if ($parsedYaml === false) {
            return $result;
        }
        
        // Extract basic metadata
        if (isset($parsedYaml['title'])) {
            $result['title'] = $parsedYaml['title'];
        }
        
        if (isset($parsedYaml['author'])) {
            $result['author'] = $parsedYaml['author'];
        }
        
        if (isset($parsedYaml['version'])) {
            $result['version'] = $parsedYaml['version'];
        }
        
        if (isset($parsedYaml['tag-type'])) {
            $result['tag-type'] = $parsedYaml['tag-type'];
        }
        
        if (isset($parsedYaml['auto-update'])) {
            $result['auto-update'] = $parsedYaml['auto-update'];
        }
        
        // Extract tags
        if (isset($parsedYaml['tags']) && is_array($parsedYaml['tags'])) {
            foreach ($parsedYaml['tags'] as $tag) {
                if (is_array($tag) && count($tag) >= 2) {
                    $result['tags'][] = $tag;
                }
            }
        }
        
        return $result;
    }
}

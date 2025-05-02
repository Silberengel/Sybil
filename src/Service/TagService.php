<?php

namespace Sybil\Service;

use Sybil\Utilities\TagUtility;
use Sybil\Utilities\YamlUtility;

/**
 * Service for managing tags
 * 
 * This service handles tag-related functionality, such as creating and
 * formatting d-tags and creating event tags.
 * 
 * @deprecated Use the TagUtility, KeyUtility, and YamlUtility classes directly instead.
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
     * @deprecated Use TagUtility::constructDTagPublication() instead
     */
    public function constructDTagPublication(string $title, string $author = "unknown", string $version = "1"): string
    {
        return TagUtility::constructDTagPublication($title, $author, $version);
    }
    
    /**
     * Construct a d-tag for articles
     *
     * @param string $title The title of the article
     * @return string The formatted d-tag
     * @deprecated Use TagUtility::constructDTagArticles() instead
     */
    public function constructDTagArticles(string $title): string
    {
        return TagUtility::constructDTagArticles($title);
    }
    
    /**
     * Normalize a component for use in a d-tag
     *
     * @param string $component The component to normalize
     * @return string The normalized component
     * @deprecated Use TagUtility::normalizeTagComponent() instead
     */
    public function normalizeTagComponent(string $component): string
    {
        return TagUtility::normalizeTagComponent($component);
    }
    
    /**
     * Format a d-tag according to the required rules
     *
     * @param string $dTag The raw d-tag to format
     * @return string The formatted d-tag
     * @deprecated Use TagUtility::formatDTag() instead
     */
    public function formatDTag(string $dTag): string
    {
        return TagUtility::formatDTag($dTag);
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
     * @deprecated Use TagUtility::createPublicationTags() instead
     */
    public function createPublicationTags(string $dTag, string $title, string $author, string $version, array $additionalTags = []): array
    {
        return TagUtility::createPublicationTags($dTag, $title, $author, $version, $additionalTags);
    }
    
    /**
     * Create section tags
     *
     * @param string $dTag The d-tag
     * @param string $title The title
     * @param string $author The author
     * @param array $additionalTags Additional tags to include
     * @return array The section tags
     * @deprecated Use TagUtility::createSectionTags() instead
     */
    public function createSectionTags(string $dTag, string $title, string $author, array $additionalTags = []): array
    {
        return TagUtility::createSectionTags($dTag, $title, $author, $additionalTags);
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
     * @deprecated Use TagUtility::addATags() instead
     */
    public function addATags(array $eventIds, array $dTags, string $kind, string $publicHex, string $relay): array
    {
        return TagUtility::addATags($eventIds, $dTags, $kind, $publicHex, $relay);
    }
    
    /**
     * Add e-tags to an event
     *
     * @param array $eventIds The event IDs
     * @return array The e-tags
     * @deprecated Use TagUtility::addETags() instead
     */
    public function addETags(array $eventIds): array
    {
        return TagUtility::addETags($eventIds);
    }
    
    /**
     * Create tags from YAML data
     *
     * @param string $yamlSnippet The YAML snippet
     * @return array The tags
     * @deprecated Use YamlUtility::extractMetadata() instead
     */
    public function createTagsFromYaml(string $yamlSnippet): array
    {
        // Extract YAML content
        $yamlContent = YamlUtility::extractYamlContent($yamlSnippet);
        
        // Extract metadata from YAML content
        return YamlUtility::extractMetadata($yamlContent);
    }
}

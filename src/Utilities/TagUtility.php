<?php
/**
 * Class TagUtility
 * 
 * This class provides utility functions for working with Nostr tags:
 * - Creating and formatting d-tags
 * - Creating section tags, publication tags, a-tags, and e-tags
 * - Normalizing tag components
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\TagUtility;
 * 
 * // Create a d-tag for a publication
 * $dTag = TagUtility::constructDTagPublication('My Publication', 'John Doe', '1.0');
 * 
 * // Create tags for a section event
 * $tags = TagUtility::createSectionTags($dTag, 'My Publication', 'John Doe');
 * 
 * // Create tags for a publication event
 * $tags = TagUtility::createPublicationTags($dTag, 'My Publication', 'John Doe', '1.0');
 * 
 * // Add a-tags for section references
 * $aTags = TagUtility::addATags($sectionEvents, $sectionDtags, '30023', $publicHex, $defaultRelay);
 * 
 * // Add e-tags for section references
 * $eTags = TagUtility::addETags($sectionEvents);
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see KeyUtility For key-related operations
 * @see YamlUtility For YAML-related operations
 */

namespace Sybil\Utilities;

class TagUtility
{
    /**
     * Constructs a d tag from title, author, and version.
     *
     * D tag rules:
     * - Consists of the title, author (if included), and version (if included)
     * - All words in ASCII or URL-encoding (converted from UTF-8 where necessary)
     * - Words separated by a hyphen
     * - Words normalized to lowercase, and all punctuation and whitespace removed, except "."
     * - Author preceded with "by"
     * - Version preceded with "v"
     * - Not longer than 75 characters in length
     * 
     * Valid d-tags formats:
     * - title
     * - title-by-author
     * - title-by-author-v-version
     * 
     * Example: aesops-fables-by-aesop-v-5.0
     *
     * @param string $title The title of the publication
     * @param string $author The author name (optional, defaults to "unknown")
     * @param string $version The version number (optional)
     * @return string The formatted d-tag
     */
    public static function constructDTagPublication(string $title, string $author = "unknown", string $version = "1"): string
    {
        // Replace spaces with dashes
        $normalizedTitle = self::normalizeTagComponent($title);
        $normalizedAuthor = self::normalizeTagComponent($author);
        
        // Construct the base d-tag
        $dTag = $normalizedTitle . "-by-" . $normalizedAuthor;
        
        // Add version if provided
        if (!empty($version)) {
            $normalizedVersion = self::normalizeTagComponent($version);
            $dTag .= "-v-" . $normalizedVersion;
        }
        
        // Final formatting: lowercase and remove punctuation except and hyphens
        return self::formatDTag($dTag);
    }

    /**
     * Constructs a d tag for articles.
     *
     * @param string $title The title of the article
     * @return string The formatted d-tag
     */
    public static function constructDTagArticles(string $title): string
    {
        $publicHex = KeyUtility::getNpub();

        // Replace spaces with dashes
        $normalizedTitle = self::normalizeTagComponent($title);
        
        // Construct the base d-tag
        $dTag = $normalizedTitle . "-by-" . substr($publicHex, 10);
        
        // Final formatting: lowercase and remove punctuation except hyphens
        return self::formatDTag($dTag);
    }

    /**
     * Normalizes a component for use in a d-tag by replacing spaces with hyphens.
     *
     * @param string $component The component to normalize
     * @return string The normalized component
     */
    public static function normalizeTagComponent(string $component): string
    {
        return strval(preg_replace('/\s+/', '-', $component));
    }

    /**
     * Formats a d-tag according to the required rules.
     *
     * @param string $dTag The raw d-tag to format
     * @return string The formatted d-tag
     */
    public static function formatDTag(string $dTag): string
    {
        // Convert to UTF-8, lowercase, and remove all punctuation except periods and hyphens
        return substr(
            strtolower(
            preg_replace(
                "/(?![.-])\p{P}/u", 
                "", 
                mb_convert_encoding($dTag, 'UTF-8', mb_list_encodings())
            )
            ), 0, 75);
    }
    
    /**
     * Creates tags for a section event
     * 
     * @param string $dTag The d-tag
     * @param string $title The title
     * @param string $author The author
     * @param array $optionaltags Additional tags to include
     * @return array The tags array
     */
    public static function createSectionTags(
        string $dTag, 
        string $title, 
        string $author, 
        array $optionaltags = []
    ): array {
        $tags = [];
        $tags[] = ['d', $dTag];
        $tags[] = ['title', $title];
        $tags[] = ['author', $author];
        $tags[] = ['m', 'text/asciidoc'];
        $tags[] = ['M', 'article/publication-content/replaceable'];
        
        // Merge with optional tags
        if (!empty($optionaltags)) {
            $tags = array_merge($tags, $optionaltags);
        }
        
        return $tags;
    }
    
    /**
     * Creates tags for a publication event
     * 
     * @param string $dTag The d-tag
     * @param string $title The title
     * @param string $author The author
     * @param string $version The version
     * @param array $eventTags Additional tags to include
     * @return array The tags array
     */
    public static function createPublicationTags(
        string $dTag, 
        string $title, 
        string $author, 
        string $version,
        array $eventTags = []
    ): array {
        $tags = [];
        $tags[] = ['d', $dTag];
        $tags[] = ['title', $title];
        $tags[] = ['author', $author];
        $tags[] = ['version', $version];
        $tags[] = ['m', 'application/json'];
        $tags[] = ['M', 'meta-data/index/replaceable'];
        
        // Merge with event tags
        if (!empty($eventTags)) {
            $tags = array_merge($tags, $eventTags);
        }
        
        return $tags;
    }
    
    /**
     * Adds a-tags for section references
     * 
     * @param array $sectionEvents The section event IDs
     * @param array $sectionDtags The section d-tags
     * @param string $sectionEventKind The section event kind
     * @param string $publicHex The public hex key
     * @param string $defaultRelay The default relay
     * @return array The tags with a-tags added
     */
    public static function addATags(
        array $sectionEvents, 
        array $sectionDtags, 
        string $sectionEventKind,
        string $publicHex,
        string $defaultRelay
    ): array {
        $sectionDtagsCopy = $sectionDtags;
        $aTags = [];
        
        foreach ($sectionEvents as $eventID) {
            $dTag = array_shift($sectionDtagsCopy);
            $aTags[] = [
                'a', 
                $sectionEventKind . ':' . $publicHex . ':' . $dTag, 
                $defaultRelay, 
                $eventID
            ];
        }
        
        return $aTags;
    }
    
    /**
     * Adds e-tags for section references
     * 
     * @param array $sectionEvents The section event IDs
     * @return array The tags with e-tags added
     */
    public static function addETags(array $sectionEvents): array
    {
        $eTags = [];
        foreach ($sectionEvents as $eventID) {
            $eTags[] = ['e', $eventID];
        }
        
        return $eTags;
    }
    
    /**
     * Creates tags using YAML data extracted from .adoc file.
     * 
     * @param string $yamlSnippet The content of the yaml snippet
     * @return array The yaml-derived tags
     */
    public static function createTagsFromYaml(string $yamlSnippet): array
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

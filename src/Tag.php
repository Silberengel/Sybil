<?php

/**
 * Class Tag
 * 
 * Handles the creation and management of tags for Nostr events.
 * This class provides static methods for creating and managing different types of tags
 * used in Nostr events, including section tags, publication tags, a-tags, and e-tags.
 */
class Tag
{
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
}

<?php

namespace Sybil\Utility\MimeType;

/**
 * Utility class for managing MIME types and Nostr-specific categorizations
 * according to NKBIP-06.
 */
class MimeTypeUtility
{
    /**
     * Map of event kinds to their MIME types and Nostr categorizations
     */
    private const KIND_MIME_TYPES = [
        // Profile events
        0 => [
            'm' => 'application/json',
            'M' => 'profile/metadata/replaceable'
        ],

        // Basic notes
        1 => [
            'm' => 'text/plain',
            'M' => 'note/microblog/nonreplaceable'
        ],
        1111 => [
            'm' => 'text/plain',
            'M' => 'note/comment/nonreplaceable'
        ],

        // Delete events
        5 => [
            'm' => 'application/json',
            'M' => 'event/delete/nonreplaceable'
        ],

        // Boost events
        6 => [
            'm' => 'application/json',
            'M' => 'event/boost/nonreplaceable'
        ],

        // Longform content
        30023 => [
            'm' => 'text/markdown',
            'M' => 'article/longform/replaceable'
        ],

        // Publications
        30040 => [
            'm' => 'application/json',
            'M' => 'meta-data/index/replaceable'
        ],
        30041 => [
            'm' => 'text/asciidoc',
            'M' => 'article/publication-content/replaceable'
        ],

        // Wiki
        30818 => [
            'm' => 'text/asciidoc',
            'M' => 'article/wiki/replaceable'
        ],

        // Citations
        30 => [
            'm' => 'application/json',
            'M' => 'citation/internal/nonreplaceable'
        ],
        31 => [
            'm' => 'application/json',
            'M' => 'citation/web/nonreplaceable'
        ],
        32 => [
            'm' => 'application/json',
            'M' => 'citation/hardcopy/nonreplaceable'
        ],
        33 => [
            'm' => 'application/json',
            'M' => 'citation/prompt/nonreplaceable'
        ],

        // Highlights
        9802 => [
            'm' => 'text/plain',
            'M' => 'highlight/quote/nonreplaceable'
        ],

        // Git events
        30617 => [
            'm' => 'application/json',
            'M' => 'git/repository/replaceable'
        ],
        30618 => [
            'm' => 'application/json',
            'M' => 'git/state/replaceable'
        ],
        1617 => [
            'm' => 'text/plain',
            'M' => 'git/patch/nonreplaceable'
        ],
        1621 => [
            'm' => 'text/plain',
            'M' => 'git/issue/nonreplaceable'
        ],
        1630 => [
            'm' => 'text/plain',
            'M' => 'git/status/nonreplaceable'
        ],
        1631 => [
            'm' => 'text/plain',
            'M' => 'git/status/nonreplaceable'
        ],
        1632 => [
            'm' => 'text/plain',
            'M' => 'git/status/nonreplaceable'
        ],
        1633 => [
            'm' => 'text/plain',
            'M' => 'git/status/nonreplaceable'
        ]
    ];

    /**
     * Get MIME type and Nostr categorization for an event kind
     */
    public static function getMimeTypes(int $kind): array
    {
        return self::KIND_MIME_TYPES[$kind] ?? [
            'm' => 'application/octet-stream',
            'M' => 'unknown/unknown/unknown'
        ];
    }

    /**
     * Add MIME type tags to an event's tags array
     */
    public static function addMimeTypeTags(array &$tags, int $kind): void
    {
        $mimeTypes = self::getMimeTypes($kind);
        
        // Add m tag if not already present
        if (!self::hasTag($tags, 'm')) {
            $tags[] = ['m', $mimeTypes['m']];
        }
        
        // Add M tag if not already present
        if (!self::hasTag($tags, 'M')) {
            $tags[] = ['M', $mimeTypes['M']];
        }
    }

    /**
     * Check if a tag exists in the tags array
     */
    private static function hasTag(array $tags, string $tagName): bool
    {
        foreach ($tags as $tag) {
            if ($tag[0] === $tagName) {
                return true;
            }
        }
        return false;
    }
} 
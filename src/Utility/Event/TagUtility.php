<?php

namespace Sybil\Utility\Event;

use Sybil\Exception\TagException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Utility class for working with Nostr tags
 * 
 * This class provides utility functions for working with Nostr tags,
 * including validation, parsing, and formatting.
 */
class TagUtility
{
    private LoggerInterface $logger;
    private ?string $privateKey;

    public function __construct(
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->logger = $logger;
        $this->privateKey = $params->get('app.private_key');
    }

    /**
     * Parse a tag string into an array
     *
     * @param string $tagString The tag string to parse
     * @return array The parsed tag array
     * @throws TagException If the tag string is invalid
     */
    public function parseTag(string $tagString): array
    {
        try {
            $this->logger->debug('Parsing tag string', [
                'tag_string' => $this->sanitizeForLogging($tagString)
            ]);

            $parts = explode(',', $tagString);
            if (empty($parts)) {
                throw new TagException('Empty tag string');
            }

            $tag = array_map('trim', $parts);
            
            $this->logger->info('Tag parsed successfully', [
                'tag_type' => $tag[0] ?? 'unknown'
            ]);

            return $tag;
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse tag', [
                'error' => $e->getMessage()
            ]);
            throw new TagException('Failed to parse tag: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Format a tag array into a string
     *
     * @param array $tag The tag array to format
     * @return string The formatted tag string
     * @throws TagException If the tag array is invalid
     */
    public function formatTag(array $tag): string
    {
        try {
            $this->logger->debug('Formatting tag array', [
                'tag_type' => $tag[0] ?? 'unknown'
            ]);

            if (empty($tag)) {
                throw new TagException('Empty tag array');
            }

            $tagString = implode(',', array_map('trim', $tag));
            
            $this->logger->info('Tag formatted successfully', [
                'tag_type' => $tag[0] ?? 'unknown'
            ]);

            return $tagString;
        } catch (\Exception $e) {
            $this->logger->error('Failed to format tag', [
                'error' => $e->getMessage()
            ]);
            throw new TagException('Failed to format tag: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate a tag array
     *
     * @param array $tag The tag array to validate
     * @return bool Whether the tag is valid
     * @throws TagException If the tag is invalid
     */
    public function validateTag(array $tag): bool
    {
        try {
            $this->logger->debug('Validating tag', [
                'tag_type' => $tag[0] ?? 'unknown'
            ]);

            if (empty($tag)) {
                throw new TagException('Empty tag array');
            }

            // Validate tag type
            if (!isset($tag[0]) || !is_string($tag[0])) {
                throw new TagException('Invalid tag type');
            }

            // Validate tag data based on type
            switch ($tag[0]) {
                case 'e':
                    if (!isset($tag[1]) || !$this->isValidEventId($tag[1])) {
                        throw new TagException('Invalid event ID in tag');
                    }
                    break;
                case 'p':
                    if (!isset($tag[1]) || !$this->isValidPublicKey($tag[1])) {
                        throw new TagException('Invalid public key in tag');
                    }
                    break;
                case 't':
                    if (!isset($tag[1]) || !$this->isValidHashtag($tag[1])) {
                        throw new TagException('Invalid hashtag in tag');
                    }
                    break;
            }

            $this->logger->info('Tag validated successfully', [
                'tag_type' => $tag[0]
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to validate tag', [
                'error' => $e->getMessage()
            ]);
            throw new TagException('Failed to validate tag: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a string is a valid event ID
     *
     * @param string $eventId The event ID to validate
     * @return bool Whether the event ID is valid
     */
    private function isValidEventId(string $eventId): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $eventId) === 1;
    }

    /**
     * Check if a string is a valid public key
     *
     * @param string $publicKey The public key to validate
     * @return bool Whether the public key is valid
     */
    private function isValidPublicKey(string $publicKey): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $publicKey) === 1;
    }

    /**
     * Check if a string is a valid hashtag
     *
     * @param string $hashtag The hashtag to validate
     * @return bool Whether the hashtag is valid
     */
    private function isValidHashtag(string $hashtag): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $hashtag) === 1;
    }

    /**
     * Sanitize data for logging to prevent sensitive information leakage
     *
     * @param string $data The data to sanitize
     * @return string The sanitized data
     */
    private function sanitizeForLogging(string $data): string
    {
        // Remove any potential private key data
        if ($this->privateKey && strpos($data, $this->privateKey) !== false) {
            return '[PRIVATE_KEY_REMOVED]';
        }
        
        // Truncate long strings
        if (strlen($data) > 100) {
            return substr($data, 0, 100) . '...';
        }
        
        return $data;
    }

    /**
     * Create a pubkey tag
     *
     * @param string $pubkey The public key
     * @param string|null $relay Optional relay URL
     * @param string|null $petname Optional petname
     * @return array The pubkey tag
     */
    public static function createPubkeyTag(string $pubkey, ?string $relay = null, ?string $petname = null): array
    {
        $tag = ['p', $pubkey];
        if ($relay) {
            $tag[] = $relay;
            if ($petname) {
                $tag[] = $petname;
            }
        }
        return $tag;
    }

    /**
     * Create an event reference tag
     *
     * @param string $eventId The event ID
     * @param string|null $relay Optional relay URL
     * @param string|null $marker Optional marker
     * @return array The event reference tag
     */
    public static function createEventTag(string $eventId, ?string $relay = null, ?string $marker = null): array
    {
        $tag = ['e', $eventId];
        if ($relay) {
            $tag[] = $relay;
            if ($marker) {
                $tag[] = $marker;
            }
        }
        return $tag;
    }

    /**
     * Create a hashtag tag
     *
     * @param string $hashtag The hashtag (without #)
     * @return array The hashtag tag
     */
    public static function createHashtagTag(string $hashtag): array
    {
        return ['t', trim($hashtag, '#')];
    }

    /**
     * Create a relay tag
     *
     * @param string $relay The relay URL
     * @return array The relay tag
     */
    public static function createRelayTag(string $relay): array
    {
        return ['relay', $relay];
    }

    /**
     * Create a subject tag
     *
     * @param string $subject The subject
     * @return array The subject tag
     */
    public static function createSubjectTag(string $subject): array
    {
        return ['subject', $subject];
    }

    /**
     * Create a d-tag (unique identifier)
     *
     * @param string $identifier The unique identifier
     * @return array The d-tag
     */
    public static function createDTag(string $identifier): array
    {
        return ['d', $identifier];
    }

    /**
     * Get all tags of a specific type from a tag array
     *
     * @param array $tags The array of tags
     * @param string $type The tag type to find
     * @return array The matching tags
     */
    public static function getTagsByType(array $tags, string $type): array
    {
        return array_filter($tags, function($tag) use ($type) {
            return is_array($tag) && !empty($tag) && $tag[0] === $type;
        });
    }

    /**
     * Get the first tag value of a specific type
     *
     * @param array $tags The array of tags
     * @param string $type The tag type to find
     * @return string|null The tag value or null if not found
     */
    public static function getFirstTagValue(array $tags, string $type): ?string
    {
        $matchingTags = self::getTagsByType($tags, $type);
        if (!empty($matchingTags)) {
            $firstTag = reset($matchingTags);
            return $firstTag[1] ?? null;
        }
        return null;
    }

    /**
     * Sort tags by type
     *
     * @param array $tags The array of tags to sort
     * @return array The sorted tags
     */
    public static function sortTags(array $tags): array
    {
        usort($tags, function($a, $b) {
            if (!is_array($a) || !is_array($b) || empty($a) || empty($b)) {
                return 0;
            }
            return strcmp($a[0], $b[0]);
        });
        return $tags;
    }

    /**
     * Validate a tag array
     *
     * @param array $tag The tag to validate
     * @return bool Whether the tag is valid
     */
    public static function isValidTag(array $tag): bool
    {
        return is_array($tag) && !empty($tag) && is_string($tag[0]);
    }
} 
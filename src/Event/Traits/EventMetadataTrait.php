<?php

namespace Sybil\Event\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Exception\EventMetadataException;
use Sybil\Exception\ValidationException;
use Sybil\Utility\Validation\TagValidator;
use Sybil\Utility\Security\DataSanitizer;

trait EventMetadataTrait
{
    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var TagValidator Tag validator
     */
    protected TagValidator $validator;

    /**
     * @var DataSanitizer Data sanitizer
     */
    protected DataSanitizer $sanitizer;

    /**
     * Initialize the trait
     */
    protected function initializeEventMetadata(?LoggerInterface $logger = null): void
    {
        $this->logger = $logger ?? new NullLogger();
        $this->validator = new TagValidator($this->logger);
        $this->sanitizer = new DataSanitizer($this->logger);
    }

    /**
     * Get a tag value by name
     * 
     * @param string $name The tag name
     * @return string|null The tag value or null if not found
     * @throws EventMetadataException If tag retrieval fails
     */
    protected function getTagValue(string $name): ?string
    {
        $this->logger->debug('Getting tag value', ['name' => $name]);

        try {
            $this->validator->validateTagName($name);
            
            foreach ($this->tags as $tag) {
                if ($tag[0] === $name) {
                    $value = $tag[1] ?? null;
                    if ($value !== null) {
                        $value = $this->sanitizer->sanitize($value);
                    }
                    $this->logger->info('Tag value retrieved successfully', ['name' => $name]);
                    return $value;
                }
            }
            
            $this->logger->info('Tag not found', ['name' => $name]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get tag value', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventMetadataException(
                'Failed to get tag value',
                EventMetadataException::ERROR_TAG_RETRIEVAL,
                $e
            );
        }
    }

    /**
     * Set a tag value
     * 
     * @param string $name The tag name
     * @param string $value The tag value
     * @param string|null $relay The relay URL (optional)
     * @throws EventMetadataException If tag setting fails
     * @throws ValidationException If tag validation fails
     */
    protected function setTagValue(string $name, string $value, ?string $relay = null): void
    {
        $this->logger->debug('Setting tag value', [
            'name' => $name,
            'value' => $value,
            'relay' => $relay
        ]);

        try {
            $this->validator->validateTagName($name);
            $this->validator->validateTagValue($value);
            if ($relay !== null) {
                $this->validator->validateRelayUrl($relay);
            }

            // Sanitize inputs
            $name = $this->sanitizer->sanitize($name);
            $value = $this->sanitizer->sanitize($value);
            if ($relay !== null) {
                $relay = $this->sanitizer->sanitizeUrl($relay);
            }

            // Remove existing tag if it exists
            $this->tags = array_filter($this->tags, function($tag) use ($name) {
                return $tag[0] !== $name;
            });

            // Add new tag
            $tag = [$name, $value];
            if ($relay !== null) {
                $tag[] = $relay;
            }
            $this->tags[] = $tag;

            // Sort tags to ensure consistent ordering
            sort($this->tags);

            $this->logger->info('Tag value set successfully', [
                'name' => $name,
                'value' => $value,
                'relay' => $relay
            ]);
        } catch (ValidationException $e) {
            $this->logger->error('Tag validation failed', [
                'name' => $name,
                'value' => $value,
                'relay' => $relay,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to set tag value', [
                'name' => $name,
                'value' => $value,
                'relay' => $relay,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventMetadataException(
                'Failed to set tag value',
                EventMetadataException::ERROR_TAG_SETTING,
                $e
            );
        }
    }

    /**
     * Remove a tag by name
     * 
     * @param string $name The tag name
     * @throws EventMetadataException If tag removal fails
     * @throws ValidationException If tag validation fails
     */
    protected function removeTag(string $name): void
    {
        $this->logger->debug('Removing tag', ['name' => $name]);

        try {
            $this->validator->validateTagName($name);
            
            $originalCount = count($this->tags);
            $this->tags = array_filter($this->tags, function($tag) use ($name) {
                return $tag[0] !== $name;
            });
            
            if (count($this->tags) < $originalCount) {
                $this->logger->info('Tag removed successfully', ['name' => $name]);
            } else {
                $this->logger->info('Tag not found for removal', ['name' => $name]);
            }
        } catch (ValidationException $e) {
            $this->logger->error('Tag validation failed', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove tag', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventMetadataException(
                'Failed to remove tag',
                EventMetadataException::ERROR_TAG_REMOVAL,
                $e
            );
        }
    }

    /**
     * Check if a tag exists
     * 
     * @param string $name The tag name
     * @return bool True if the tag exists
     * @throws EventMetadataException If tag check fails
     * @throws ValidationException If tag validation fails
     */
    protected function hasTag(string $name): bool
    {
        $this->logger->debug('Checking tag existence', ['name' => $name]);

        try {
            $this->validator->validateTagName($name);
            
            foreach ($this->tags as $tag) {
                if ($tag[0] === $name) {
                    $this->logger->info('Tag exists', ['name' => $name]);
                    return true;
                }
            }
            
            $this->logger->info('Tag does not exist', ['name' => $name]);
            return false;
        } catch (ValidationException $e) {
            $this->logger->error('Tag validation failed', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check tag existence', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventMetadataException(
                'Failed to check tag existence',
                EventMetadataException::ERROR_TAG_CHECK,
                $e
            );
        }
    }

    /**
     * Get all tag values for a given name
     * 
     * @param string $name The tag name
     * @return array The tag values
     * @throws EventMetadataException If tag retrieval fails
     * @throws ValidationException If tag validation fails
     */
    protected function getTagValues(string $name): array
    {
        $this->logger->debug('Getting all tag values', ['name' => $name]);

        try {
            $this->validator->validateTagName($name);
            
            $values = [];
            foreach ($this->tags as $tag) {
                if ($tag[0] === $name && isset($tag[1])) {
                    $values[] = $this->sanitizer->sanitize($tag[1]);
                }
            }
            
            $this->logger->info('Tag values retrieved successfully', [
                'name' => $name,
                'count' => count($values)
            ]);
            return $values;
        } catch (ValidationException $e) {
            $this->logger->error('Tag validation failed', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get tag values', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventMetadataException(
                'Failed to get tag values',
                EventMetadataException::ERROR_TAG_RETRIEVAL,
                $e
            );
        }
    }
} 
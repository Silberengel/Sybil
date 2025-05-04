<?php

namespace Sybil\Event\Traits;

use swentel\nostr\Event\Event;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Exception\EventBuildingException;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;
use Sybil\Utility\Validation\EventValidator;
use Sybil\Utility\Security\SignatureVerifier;
use Sybil\Utility\Log\LoggerFactory;

trait EventBuildingTrait
{
    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var EventValidator Event validator
     */
    protected EventValidator $validator;

    /**
     * @var SignatureVerifier Signature verifier
     */
    protected SignatureVerifier $signatureVerifier;

    /**
     * Initialize the trait
     */
    protected function initializeEventBuilding(?LoggerInterface $logger = null): void
    {
        $this->logger = $logger ?? new NullLogger();
        $this->validator = new EventValidator($this->logger);
        $this->signatureVerifier = new SignatureVerifier($this->logger);
    }

    /**
     * Build the event with basic properties
     * 
     * @return Event The configured event
     * @throws EventBuildingException If event building fails
     * @throws ValidationException If event validation fails
     */
    protected function buildBasicEvent(): Event
    {
        $this->logger->debug('Building basic event');

        try {
            $event = new Event();
            $event->setKind($this->kind);
            $event->setContent($this->content);
            $event->setCreatedAt($this->createdAt);
            $event->setPubkey($this->pubkey);

            // Validate event data
            $this->validator->validate($event);
            
            $this->logger->info('Basic event built successfully');
            return $event;
        } catch (ValidationException $e) {
            $this->logger->error('Event validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to build basic event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventBuildingException(
                'Failed to build basic event',
                EventBuildingException::ERROR_BUILDING,
                $e
            );
        }
    }

    /**
     * Add tags to the event
     * 
     * @param Event $event The event to add tags to
     * @param array $tags The tags to add
     * @throws EventBuildingException If adding tags fails
     */
    protected function addTagsToEvent(Event $event, array $tags): void
    {
        $this->logger->debug('Adding tags to event', ['tag_count' => count($tags)]);

        try {
            $event->setTags($tags);
            $this->logger->info('Tags added to event successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to add tags to event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventBuildingException(
                'Failed to add tags to event',
                EventBuildingException::ERROR_TAGS,
                $e
            );
        }
    }

    /**
     * Extract hashtags from content
     * 
     * @param string $content The content
     * @return array The hashtags
     * @throws EventBuildingException If hashtag extraction fails
     */
    protected function extractHashtags(string $content): array
    {
        $this->logger->debug('Extracting hashtags from content', ['content_length' => strlen($content)]);

        try {
            $hashtags = [];
            preg_match_all('/#(\w+)/', $content, $matches);
            
            if (!empty($matches[1])) {
                $hashtags = $matches[1];
            }
            
            $this->logger->info('Hashtags extracted successfully', ['hashtag_count' => count($hashtags)]);
            return $hashtags;
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract hashtags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventBuildingException(
                'Failed to extract hashtags',
                EventBuildingException::ERROR_HASHTAGS,
                $e
            );
        }
    }

    /**
     * Add hashtags to event tags
     * 
     * @param array &$tags The tags array to modify
     * @param string $content The content to extract hashtags from
     * @throws EventBuildingException If adding hashtags fails
     */
    protected function addHashtagsToTags(array &$tags, string $content): void
    {
        $this->logger->debug('Adding hashtags to tags');

        try {
            $hashtags = $this->extractHashtags($content);
            foreach ($hashtags as $hashtag) {
                $tags[] = ['t', $hashtag];
            }
            $this->logger->info('Hashtags added to tags successfully', ['hashtag_count' => count($hashtags)]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add hashtags to tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventBuildingException(
                'Failed to add hashtags to tags',
                EventBuildingException::ERROR_HASHTAGS,
                $e
            );
        }
    }

    /**
     * Verify the event signature
     *
     * @param Event $event The event to verify
     * @return bool True if the signature is valid
     * @throws AuthenticationException If verification fails
     */
    protected function verifyEventSignature(Event $event): bool
    {
        $this->logger->debug('Verifying event signature');

        try {
            $result = $this->signatureVerifier->verify($event);
            
            if ($result) {
                $this->logger->info('Event signature verified successfully');
            } else {
                $this->logger->warning('Event signature verification failed');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Event signature verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new AuthenticationException(
                'Event signature verification failed',
                AuthenticationException::ERROR_SIGNATURE_VERIFICATION,
                $e
            );
        }
    }
} 
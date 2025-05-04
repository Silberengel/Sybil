<?php

namespace Sybil\Event\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Exception\EventSerializationException;
use Sybil\Exception\ValidationException;
use Sybil\Utility\Validation\EventValidator;
use Sybil\Utility\Security\DataSanitizer;
use Sybil\Utility\Security\SignatureVerifier;
use Sybil\Utility\Log\LoggerFactory;

trait EventSerializationTrait
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
     * @var DataSanitizer Data sanitizer
     */
    protected DataSanitizer $sanitizer;

    /**
     * @var SignatureVerifier Signature verifier
     */
    protected SignatureVerifier $signatureVerifier;

    /**
     * Initialize the trait
     */
    protected function initializeEventSerialization(?LoggerInterface $logger = null): void
    {
        $this->logger = $logger ?? new NullLogger();
        $this->validator = new EventValidator($this->logger);
        $this->sanitizer = new DataSanitizer($this->logger);
        $this->signatureVerifier = new SignatureVerifier($this->logger);
    }

    /**
     * Convert the event to an array format
     * 
     * @return array The event data as an array
     * @throws EventSerializationException If serialization fails
     * @throws ValidationException If event validation fails
     */
    public function toArray(): array
    {
        $this->logger->debug('Converting event to array');

        try {
            // Validate the event before serialization
            $this->validator->validate($this);

            // Sanitize the data
            $data = [
                'id' => $this->sanitizer->sanitize($this->id),
                'pubkey' => $this->sanitizer->sanitize($this->pubkey),
                'created_at' => $this->createdAt,
                'kind' => $this->kind,
                'content' => $this->sanitizer->sanitize($this->content),
                'tags' => array_map(function($tag) {
                    return array_map(function($value) {
                        return $this->sanitizer->sanitize($value);
                    }, $tag);
                }, $this->tags),
                'sig' => $this->sanitizer->sanitize($this->sig),
                'published' => $this->published,
                'published_at' => $this->publishedAt,
            ];

            $this->logger->info('Event converted to array successfully');
            return $data;
        } catch (ValidationException $e) {
            $this->logger->error('Event validation failed during serialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to convert event to array', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventSerializationException(
                'Failed to convert event to array',
                EventSerializationException::ERROR_SERIALIZATION,
                $e
            );
        }
    }

    /**
     * Create an event from an array
     * 
     * @param array $data The event data
     * @return static The created event
     * @throws EventSerializationException If deserialization fails
     * @throws ValidationException If event validation fails
     */
    public static function fromArray(array $data): self
    {
        $logger = LoggerFactory::createLogger();
        $logger->debug('Creating event from array');

        try {
            $event = new static();
            
            // Validate required fields
            if (empty($data['pubkey'])) {
                throw new ValidationException('Missing required field: pubkey');
            }
            if (!isset($data['kind'])) {
                throw new ValidationException('Missing required field: kind');
            }

            // Set and sanitize the data
            $event->id = $data['id'] ?? '';
            $event->pubkey = $data['pubkey'];
            $event->createdAt = $data['created_at'] ?? time();
            $event->kind = (int)$data['kind'];
            $event->content = $data['content'] ?? '';
            $event->tags = $data['tags'] ?? [];
            $event->sig = $data['sig'] ?? '';
            $event->published = $data['published'] ?? false;
            $event->publishedAt = $data['published_at'] ?? null;

            // Validate the event
            $validator = new EventValidator($logger);
            $validator->validate($event);

            // Verify the signature if present
            if (!empty($event->sig)) {
                $signatureVerifier = new SignatureVerifier($logger);
                if (!$signatureVerifier->verify($event)) {
                    throw new ValidationException('Invalid event signature');
                }
            }

            $logger->info('Event created from array successfully');
            return $event;
        } catch (ValidationException $e) {
            $logger->error('Event validation failed during deserialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $logger->error('Failed to create event from array', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventSerializationException(
                'Failed to create event from array',
                EventSerializationException::ERROR_DESERIALIZATION,
                $e
            );
        }
    }
} 
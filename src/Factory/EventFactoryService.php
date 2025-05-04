<?php

namespace Sybil\Factory;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Entity\NostrEvent;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Event\LongformEvent;
use Sybil\Event\PublicationEvent;
use Sybil\Event\SectionEvent;
use Sybil\Event\TextNoteEvent;
use Sybil\Event\WikiEvent;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;
use Sybil\Utility\Log\LoggerFactory;
use Sybil\Utility\Security\KeyValidator;
use Sybil\Utility\Security\SignatureVerifier;
use Sybil\Utility\Validation\EventValidator;

/**
 * Factory for creating Nostr events
 */
class EventFactory
{
    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var KeyValidator Key validator
     */
    protected KeyValidator $keyValidator;

    /**
     * @var SignatureVerifier Signature verifier
     */
    protected SignatureVerifier $signatureVerifier;

    /**
     * @var EventValidator Event validator
     */
    protected EventValidator $eventValidator;

    /**
     * Constructor
     * 
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? LoggerFactory::createLogger('event_factory');
        $this->keyValidator = new KeyValidator($this->logger);
        $this->signatureVerifier = new SignatureVerifier($this->logger);
        $this->eventValidator = new EventValidator($this->logger);
    }

    public function createEvent(array $data): NostrEvent
    {
        $event = new NostrEvent();
        $event->setId($data['id'] ?? null);
        $event->setPubkey($data['pubkey'] ?? null);
        $event->setCreatedAt($data['created_at'] ?? time());
        $event->setKind($data['kind'] ?? 1);
        $event->setTags($data['tags'] ?? []);
        $event->setContent($data['content'] ?? '');
        $event->setSig($data['sig'] ?? null);
        $event->setPublished(false);

        return $event;
    }

    /**
     * Create a text note event
     * 
     * @param string $content The note content
     * @param string $privateKey The private key
     * @param array $tags Optional tags
     * @return TextNoteEvent The created event
     * @throws ValidationException If validation fails
     * @throws AuthenticationException If authentication fails
     */
    public function createTextNote(string $content, string $privateKey, array $tags = []): TextNoteEvent
    {
        $this->logger->debug('Creating text note event', ['content_length' => strlen($content)]);

        try {
            if (!$this->keyValidator->validatePrivateKey($privateKey)) {
                throw new AuthenticationException('Invalid private key');
            }

            $event = new TextNoteEvent($this->logger);
            $event->setContent($content);
            $event->setTags($tags);

            $this->eventValidator->validate($event);
            $this->signatureVerifier->sign($event, $privateKey);

            $this->logger->info('Text note event created successfully', [
                'event_id' => $event->getId(),
                'pubkey' => $event->getPubkey()
            ]);

            return $event;
        } catch (ValidationException $e) {
            $this->logger->error('Failed to create text note event: validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('Failed to create text note event: authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create text note event: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException('Failed to create text note event: ' . $e->getMessage());
        }
    }

    /**
     * Create a longform event
     * 
     * @param string $content The article content
     * @param string $privateKey The private key
     * @param array $tags Optional tags
     * @return LongformEvent The created event
     * @throws ValidationException If validation fails
     * @throws AuthenticationException If authentication fails
     */
    public function createLongform(string $content, string $privateKey, array $tags = []): LongformEvent
    {
        $this->logger->debug('Creating longform event', ['content_length' => strlen($content)]);

        try {
            if (!$this->keyValidator->validatePrivateKey($privateKey)) {
                throw new AuthenticationException('Invalid private key');
            }

            $event = new LongformEvent($this->logger);
            $event->setContent($content);
            $event->setTags($tags);

            $this->eventValidator->validate($event);
            $this->signatureVerifier->sign($event, $privateKey);

            $this->logger->info('Longform event created successfully', [
                'event_id' => $event->getId(),
                'pubkey' => $event->getPubkey()
            ]);

            return $event;
        } catch (ValidationException $e) {
            $this->logger->error('Failed to create longform event: validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('Failed to create longform event: authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create longform event: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException('Failed to create longform event: ' . $e->getMessage());
        }
    }

    /**
     * Create a publication event
     * 
     * @param string $content The publication content
     * @param string $privateKey The private key
     * @param array $tags Optional tags
     * @return PublicationEvent The created event
     * @throws ValidationException If validation fails
     * @throws AuthenticationException If authentication fails
     */
    public function createPublication(string $content, string $privateKey, array $tags = []): PublicationEvent
    {
        $this->logger->debug('Creating publication event', ['content_length' => strlen($content)]);

        try {
            if (!$this->keyValidator->validatePrivateKey($privateKey)) {
                throw new AuthenticationException('Invalid private key');
            }

            $event = new PublicationEvent($this->logger);
            $event->setContent($content);
            $event->setTags($tags);

            $this->eventValidator->validate($event);
            $this->signatureVerifier->sign($event, $privateKey);

            $this->logger->info('Publication event created successfully', [
                'event_id' => $event->getId(),
                'pubkey' => $event->getPubkey()
            ]);

            return $event;
        } catch (ValidationException $e) {
            $this->logger->error('Failed to create publication event: validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('Failed to create publication event: authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create publication event: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException('Failed to create publication event: ' . $e->getMessage());
        }
    }

    /**
     * Create a section event
     * 
     * @param string $content The section content
     * @param string $privateKey The private key
     * @param array $tags Optional tags
     * @return SectionEvent The created event
     * @throws ValidationException If validation fails
     * @throws AuthenticationException If authentication fails
     */
    public function createSection(string $content, string $privateKey, array $tags = []): SectionEvent
    {
        $this->logger->debug('Creating section event', ['content_length' => strlen($content)]);

        try {
            if (!$this->keyValidator->validatePrivateKey($privateKey)) {
                throw new AuthenticationException('Invalid private key');
            }

            $event = new SectionEvent($this->logger);
            $event->setContent($content);
            $event->setTags($tags);

            $this->eventValidator->validate($event);
            $this->signatureVerifier->sign($event, $privateKey);

            $this->logger->info('Section event created successfully', [
                'event_id' => $event->getId(),
                'pubkey' => $event->getPubkey()
            ]);

            return $event;
        } catch (ValidationException $e) {
            $this->logger->error('Failed to create section event: validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('Failed to create section event: authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create section event: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException('Failed to create section event: ' . $e->getMessage());
        }
    }

    /**
     * Create a wiki event
     * 
     * @param string $content The wiki content
     * @param string $privateKey The private key
     * @param array $tags Optional tags
     * @return WikiEvent The created event
     * @throws ValidationException If validation fails
     * @throws AuthenticationException If authentication fails
     */
    public function createWiki(string $content, string $privateKey, array $tags = []): WikiEvent
    {
        $this->logger->debug('Creating wiki event', ['content_length' => strlen($content)]);

        try {
            if (!$this->keyValidator->validatePrivateKey($privateKey)) {
                throw new AuthenticationException('Invalid private key');
            }

            $event = new WikiEvent($this->logger);
            $event->setContent($content);
            $event->setTags($tags);

            $this->eventValidator->validate($event);
            $this->signatureVerifier->sign($event, $privateKey);

            $this->logger->info('Wiki event created successfully', [
                'event_id' => $event->getId(),
                'pubkey' => $event->getPubkey()
            ]);

            return $event;
        } catch (ValidationException $e) {
            $this->logger->error('Failed to create wiki event: validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('Failed to create wiki event: authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create wiki event: unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidationException('Failed to create wiki event: ' . $e->getMessage());
        }
    }
} 
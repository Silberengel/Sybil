<?php

namespace Sybil\Entity;

use App\Repository\NostrEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use swentel\nostr\Sign\Sign;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Exception\NostrEventException;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;
use Sybil\Utility\Log\LoggerFactory;
use Sybil\Utility\Validation\EventValidator;
use Sybil\Utility\Security\SignatureVerifier;

#[ORM\Entity(repositoryClass: NostrEventRepository::class)]
#[ORM\Table(name: 'nostr_events')]
class NostrEventEntity
{
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    #[Assert\Length(exactly: 64)]
    private string $id;

    #[ORM\Column(length: 64)]
    #[Assert\Length(exactly: 64)]
    private string $pubkey;

    #[ORM\Column]
    #[Assert\Positive]
    private int $createdAt;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $kind;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'json')]
    private array $tags;

    #[ORM\Column(length: 128)]
    #[Assert\Length(exactly: 128)]
    private string $sig;

    #[ORM\Column]
    private bool $published = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @var LoggerInterface Logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var EventValidator Event validator
     */
    private EventValidator $validator;

    /**
     * @var SignatureVerifier Signature verifier
     */
    private SignatureVerifier $signatureVerifier;

    /**
     * Constructor
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->validator = new EventValidator($this->logger);
        $this->signatureVerifier = new SignatureVerifier($this->logger);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->logger->debug('Setting event ID', ['id' => $id]);
        $this->id = $id;
        return $this;
    }

    public function getPubkey(): string
    {
        return $this->pubkey;
    }

    public function setPubkey(string $pubkey): self
    {
        $this->logger->debug('Setting event public key', ['pubkey' => $pubkey]);
        $this->pubkey = $pubkey;
        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): self
    {
        $this->logger->debug('Setting event creation time', ['created_at' => $createdAt]);
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    public function setKind(int $kind): self
    {
        $this->logger->debug('Setting event kind', ['kind' => $kind]);
        $this->kind = $kind;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->logger->debug('Setting event content', ['content_length' => strlen($content)]);
        $this->content = $content;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->logger->debug('Setting event tags', ['tag_count' => count($tags)]);
        $this->tags = $tags;
        return $this;
    }

    public function getSig(): string
    {
        return $this->sig;
    }

    public function setSig(string $sig): self
    {
        $this->logger->debug('Setting event signature', ['sig_length' => strlen($sig)]);
        $this->sig = $sig;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->logger->debug('Setting event published status', ['published' => $published]);
        $this->published = $published;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->logger->debug('Setting event published time', ['published_at' => $publishedAt?->format('Y-m-d H:i:s')]);
        $this->publishedAt = $publishedAt;
        return $this;
    }

    /**
     * Convert event to array
     *
     * @return array<string, mixed> Event data
     * @throws NostrEventException If conversion fails
     */
    public function toArray(): array
    {
        try {
            $this->logger->debug('Converting event to array');
            return [
                'id' => $this->id,
                'pubkey' => $this->pubkey,
                'created_at' => $this->createdAt,
                'kind' => $this->kind,
                'content' => $this->content,
                'tags' => $this->tags,
                'sig' => $this->sig
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to convert event to array', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrEventException(
                'Failed to convert event to array',
                NostrEventException::ERROR_CONVERSION,
                $e
            );
        }
    }

    /**
     * Create event from array
     *
     * @param array<string, mixed> $data Event data
     * @return self Event instance
     * @throws ValidationException If data is invalid
     * @throws NostrEventException If conversion fails
     */
    public static function fromArray(array $data): self
    {
        $logger = LoggerFactory::createLogger();
        $logger->debug('Creating event from array', ['data_keys' => array_keys($data)]);

        try {
            if (!isset($data['id'], $data['pubkey'], $data['created_at'], $data['kind'], $data['content'], $data['tags'], $data['sig'])) {
                $logger->error('Missing required fields in event data', ['data' => $data]);
                throw new ValidationException(
                    'Missing required fields in event data',
                    ValidationException::ERROR_MISSING_FIELDS
                );
            }

            $event = new self($logger);
            $event->setId($data['id'])
                ->setPubkey($data['pubkey'])
                ->setCreatedAt($data['created_at'])
                ->setKind($data['kind'])
                ->setContent($data['content'])
                ->setTags($data['tags'])
                ->setSig($data['sig']);

            // Validate event data
            $event->validator->validate($event);

            return $event;
        } catch (ValidationException $e) {
            $logger->error('Event validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $logger->error('Failed to create event from array', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrEventException(
                'Failed to create event from array',
                NostrEventException::ERROR_CONVERSION,
                $e
            );
        }
    }

    /**
     * Verify the event signature
     *
     * @return bool True if the signature is valid
     * @throws AuthenticationException If verification fails
     */
    public function verifySignature(): bool
    {
        $this->logger->debug('Verifying event signature');

        try {
            $event = new \swentel\nostr\Event\Event();
            $event->setId($this->id);
            $event->setPubkey($this->pubkey);
            $event->setCreatedAt($this->createdAt);
            $event->setKind($this->kind);
            $event->setContent($this->content);
            $event->setTags($this->tags);
            $event->setSig($this->sig);

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
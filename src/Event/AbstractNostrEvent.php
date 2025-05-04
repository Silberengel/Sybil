<?php

namespace Sybil\Event;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Psr\Log\LoggerInterface;
use Sybil\Event\Traits\EventValidationTrait;
use Sybil\Event\Traits\EventPublishingTrait;
use Sybil\Event\Traits\EventBuildingTrait;
use Sybil\Event\Traits\EventIdTrait;
use Sybil\Event\Traits\EventMetadataTrait;
use Sybil\Event\Traits\EventSerializationTrait;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractNostrEvent
{
    use EventValidationTrait;
    use EventPublishingTrait;
    use EventBuildingTrait;
    use EventIdTrait;
    use EventMetadataTrait;
    use EventSerializationTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=64)
     */
    protected string $id;

    /**
     * @ORM\Column(type="string", length=64)
     * @Assert\NotBlank
     */
    protected string $pubkey;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank
     */
    protected int $createdAt;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank
     */
    protected int $kind;

    /**
     * @ORM\Column(type="text")
     * @Assert\NotBlank
     */
    protected string $content;

    /**
     * @ORM\Column(type="json")
     */
    protected array $tags = [];

    /**
     * @ORM\Column(type="string", length=128)
     */
    protected string $sig;

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $published = false;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected ?int $publishedAt = null;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->createdAt = time();
    }

    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    abstract public function getKindName(): string;

    /**
     * Validate the event data
     * 
     * @throws ValidationException If validation fails
     */
    abstract protected function validate(): void;

    /**
     * Prepare the event for publishing
     */
    abstract protected function prepare(): void;

    /**
     * Build the event
     * 
     * @return \swentel\nostr\Event\Event The configured event
     */
    abstract protected function buildEvent(): \swentel\nostr\Event\Event;

    // Getters and setters
    public function getPubkey(): string
    {
        return $this->pubkey;
    }

    public function setPubkey(string $pubkey): self
    {
        $this->pubkey = $pubkey;
        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getKind(): int
    {
        return $this->kind;
    }

    public function setKind(int $kind): self
    {
        $this->kind = $kind;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function getSig(): string
    {
        return $this->sig;
    }

    public function setSig(string $sig): self
    {
        $this->sig = $sig;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;
        return $this;
    }

    public function getPublishedAt(): ?int
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?int $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    /**
     * Convert the event to an array format
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pubkey' => $this->pubkey,
            'created_at' => $this->createdAt,
            'kind' => $this->kind,
            'content' => $this->content,
            'tags' => $this->tags,
            'sig' => $this->sig,
        ];
    }

    /**
     * Create an event from an array
     */
    public static function fromArray(array $data): self
    {
        $event = new static();
        $event->id = $data['id'] ?? '';
        $event->pubkey = $data['pubkey'] ?? '';
        $event->createdAt = $data['created_at'] ?? time();
        $event->kind = $data['kind'] ?? 0;
        $event->content = $data['content'] ?? '';
        $event->tags = $data['tags'] ?? [];
        $event->sig = $data['sig'] ?? '';
        return $event;
    }
} 
<?php

namespace Sybil\Event;

use Doctrine\ORM\Mapping as ORM;
use Psr\Log\LoggerInterface;
use Sybil\Exception\ValidationException;
use Sybil\Event\Traits\EventBuildingTrait;
use Sybil\Event\Traits\EventIdTrait;
use Sybil\Event\Traits\EventMetadataTrait;
use Sybil\Event\Traits\EventPublishingTrait;
use Sybil\Event\Traits\EventSerializationTrait;
use Sybil\Event\Traits\EventValidationTrait;

/**
 * @ORM\Entity
 */
class LongformEvent extends AbstractNostrEvent
{
    use EventBuildingTrait;
    use EventIdTrait;
    use EventMetadataTrait;
    use EventPublishingTrait;
    use EventSerializationTrait;
    use EventValidationTrait;

    /**
     * @ORM\Column(type="text")
     */
    protected string $content;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $title;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $dTag;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $author = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $image = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    protected ?array $hashtags = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $summary = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $publishedAt = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected ?string $canonicalUrl = null;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->kind = 30023; // Long-form content kind
    }

    public function getKindName(): string
    {
        return 'longform';
    }

    protected function validate(): void
    {
        if (empty($this->content)) {
            throw new ValidationException('Content cannot be empty');
        }

        if (empty($this->title)) {
            throw new ValidationException('Title cannot be empty');
        }

        if (empty($this->dTag)) {
            throw new ValidationException('d-tag cannot be empty');
        }

        if (strlen($this->content) > 100000) {
            throw new ValidationException('Content exceeds maximum length of 100000 characters');
        }

        if (strlen($this->title) > 255) {
            throw new ValidationException('Title exceeds maximum length of 255 characters');
        }

        if (strlen($this->dTag) > 255) {
            throw new ValidationException('d-tag exceeds maximum length of 255 characters');
        }

        if ($this->summary !== null && strlen($this->summary) > 255) {
            throw new ValidationException('Summary exceeds maximum length of 255 characters');
        }

        if ($this->canonicalUrl !== null && strlen($this->canonicalUrl) > 255) {
            throw new ValidationException('Canonical URL exceeds maximum length of 255 characters');
        }
    }

    protected function prepare(): void
    {
        // Add required tags
        $this->setTagValue('d', $this->dTag);
        $this->setTagValue('title', $this->title);
        $this->setTagValue('p', $this->pubkey);

        // Add optional tags if available
        if ($this->author !== null) {
            $this->setTagValue('author', $this->author);
        }

        if ($this->image !== null) {
            $this->setTagValue('image', $this->image);
        }

        if ($this->summary !== null) {
            $this->setTagValue('summary', $this->summary);
        }

        if ($this->publishedAt !== null) {
            $this->setTagValue('published_at', $this->publishedAt);
        }

        if ($this->canonicalUrl !== null) {
            $this->setTagValue('canonical_url', $this->canonicalUrl);
        }

        if ($this->hashtags !== null) {
            foreach ($this->hashtags as $tag) {
                $this->setTagValue('t', $tag);
            }
        }

        // Sort tags for consistent ordering
        sort($this->tags);
    }

    protected function buildEvent(): \swentel\nostr\Event\Event
    {
        $event = new \swentel\nostr\Event\Event();
        $event->setKind($this->kind);
        $event->setContent($this->content);
        $event->setTags($this->tags);
        return $event;
    }

    // Getters and setters for additional properties
    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDTag(): string
    {
        return $this->dTag;
    }

    public function setDTag(string $dTag): self
    {
        $this->dTag = $dTag;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getHashtags(): ?array
    {
        return $this->hashtags;
    }

    public function setHashtags(?array $hashtags): self
    {
        $this->hashtags = $hashtags;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?string $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function setCanonicalUrl(?string $canonicalUrl): self
    {
        $this->canonicalUrl = $canonicalUrl;
        return $this;
    }
}

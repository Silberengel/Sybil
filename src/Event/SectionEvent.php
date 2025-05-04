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
use Sybil\Event\Traits\EventSigningTrait;
use Sybil\Event\Traits\EventValidationTrait;

/**
 * @ORM\Entity
 */
class SectionEvent extends AbstractNostrEvent
{
    use EventBuildingTrait;
    use EventIdTrait;
    use EventMetadataTrait;
    use EventPublishingTrait;
    use EventSerializationTrait;
    use EventSigningTrait;
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
     * @ORM\Column(type="string", length=255)
     */
    protected string $parentId;

    /**
     * @ORM\Column(type="integer")
     */
    protected int $order;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->kind = 30023; // Long-form content kind
    }

    public function getKindName(): string
    {
        return 'section';
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

        if (empty($this->parentId)) {
            throw new ValidationException('Parent ID cannot be empty');
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

        if (strlen($this->parentId) > 255) {
            throw new ValidationException('Parent ID exceeds maximum length of 255 characters');
        }
    }

    protected function prepare(): void
    {
        // Add required tags
        $this->setTagValue('d', $this->dTag);
        $this->setTagValue('title', $this->title);
        $this->setTagValue('p', $this->pubkey);
        $this->setTagValue('e', $this->parentId);
        $this->setTagValue('order', (string)$this->order);

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

    public function getParentId(): string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;
        return $this;
    }
}

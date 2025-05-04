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
class TextNoteEvent extends AbstractNostrEvent
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

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->kind = 1; // Text note kind
    }

    public function getKindName(): string
    {
        return 'text_note';
    }

    protected function validate(): void
    {
        if (empty($this->content)) {
            throw new ValidationException('Content cannot be empty');
        }

        if (strlen($this->content) > 10000) {
            throw new ValidationException('Content exceeds maximum length of 10000 characters');
        }
    }

    protected function prepare(): void
    {
        // Add required tags
        $this->setTagValue('p', $this->pubkey);

        // Add optional tags if available
        if ($this->hasTag('e')) {
            $this->setTagValue('e', $this->getTagValue('e'));
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
}

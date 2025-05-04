<?php

namespace Sybil\Utility\Validation;

use Psr\Log\LoggerInterface;
use Sybil\Entity\NostrEventEntity;
use Sybil\Exception\ValidationException;

class EventValidator
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function validate(NostrEventEntity $event): void
    {
        $this->logger->debug('Validating event', [
            'id' => $event->getId(),
            'pubkey' => $event->getPubkey(),
            'kind' => $event->getKind()
        ]);

        // Basic validation rules
        if (strlen($event->getId()) !== 64) {
            throw new ValidationException('Event ID must be 64 characters long');
        }

        if (strlen($event->getPubkey()) !== 64) {
            throw new ValidationException('Public key must be 64 characters long');
        }

        if (strlen($event->getSig()) !== 128) {
            throw new ValidationException('Signature must be 128 characters long');
        }

        if ($event->getKind() < 0) {
            throw new ValidationException('Event kind must be non-negative');
        }

        if ($event->getCreatedAt() <= 0) {
            throw new ValidationException('Created at timestamp must be positive');
        }

        $this->logger->info('Event validation successful');
    }
} 
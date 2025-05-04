<?php

namespace Sybil\Utility\Security;

use Psr\Log\LoggerInterface;
use swentel\nostr\Event\Event;

class SignatureVerifier
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function verify(Event $event): bool
    {
        $this->logger->debug('Verifying event signature', [
            'id' => $event->getId(),
            'pubkey' => $event->getPubkey()
        ]);

        try {
            return $event->verify();
        } catch (\Exception $e) {
            $this->logger->error('Signature verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
} 
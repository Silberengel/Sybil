<?php

namespace App\Command\Trait;

use App\Service\NostrService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait NgitCommandTrait
{
    protected function getRepositoryAddress(string $repoId, string $ownerPubkey): string
    {
        return "30617:{$ownerPubkey}:{$repoId}";
    }

    protected function validateRepositoryId(string $repoId): void
    {
        if (!preg_match('/^[a-z0-9-]+$/', $repoId)) {
            throw new \InvalidArgumentException('Repository ID must be kebab-case (lowercase letters, numbers, and hyphens)');
        }
    }

    protected function validatePubkey(string $pubkey): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $pubkey)) {
            throw new \InvalidArgumentException('Invalid public key format');
        }
    }

    protected function getStatusKind(string $status): int
    {
        return match ($status) {
            'open' => 1630,
            'applied', 'merged', 'resolved' => 1631,
            'closed' => 1632,
            'draft' => 1633,
            default => throw new \InvalidArgumentException('Invalid status. Must be one of: open, applied, merged, resolved, closed, draft')
        };
    }
} 
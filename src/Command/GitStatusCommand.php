<?php

namespace App\Command;

use App\Command\Trait\GitCommandTrait;
use App\Service\NostrService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'git-status',
    description: 'Update git issue/patch status on Nostr',
)]
class GitStatusCommand extends Command
{
    use GitCommandTrait;

    public function __construct(
        private readonly NostrService $nostrService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('event-id', null, InputOption::VALUE_REQUIRED, 'Event ID to update status for')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'New status (open, applied, merged, resolved, closed, draft)')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Status update message')
            ->addOption('repo-id', null, InputOption::VALUE_REQUIRED, 'Repository ID')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Repository owner pubkey')
            ->addOption('revision-id', null, InputOption::VALUE_REQUIRED, 'Revision event ID (for applied/merged status)')
            ->addOption('merge-commit', null, InputOption::VALUE_REQUIRED, 'Merge commit ID (for merged status)')
            ->addOption('applied-commits', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Applied commit IDs (for applied status)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $eventId = $input->getOption('event-id');
        $status = $input->getOption('status');
        $repoId = $input->getOption('repo-id');
        $ownerPubkey = $input->getOption('owner');

        $this->validateRepositoryId($repoId);
        $this->validatePubkey($ownerPubkey);

        $tags = [
            ['e', $eventId, '', 'root'],
            ['p', $ownerPubkey],
        ];

        if ($revisionId = $input->getOption('revision-id')) {
            $tags[] = ['e', $revisionId, '', 'reply'];
        }

        if ($repoId && $ownerPubkey) {
            $tags[] = ['a', $this->getRepositoryAddress($repoId, $ownerPubkey)];
        }

        if ($status === 'merged' && $mergeCommit = $input->getOption('merge-commit')) {
            $tags[] = ['merge-commit', $mergeCommit];
            $tags[] = ['r', $mergeCommit];
        }

        if ($status === 'applied') {
            foreach ($input->getOption('applied-commits') as $commit) {
                $tags[] = ['applied-as-commits', $commit];
                $tags[] = ['r', $commit];
            }
        }

        $event = $this->nostrService->createEvent(
            kind: $this->getStatusKind($status),
            content: $input->getOption('content'),
            tags: $tags
        );

        $this->nostrService->publishEvent($event);

        $output->writeln('Status updated successfully!');
        return Command::SUCCESS;
    }
} 
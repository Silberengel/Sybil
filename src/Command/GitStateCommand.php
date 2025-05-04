<?php

namespace App\Command;

use App\Service\NostrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'git-state',
    description: 'Announce git repository state (kind 30618)',
)]
class GitStateCommand extends Command
{
    public function __construct(
        private readonly NostrService $nostrService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command announces the state of a git repository using kind 30618.

This command follows NIP-34 for git repository state announcements. It creates an event that:
- Tracks the state of branches and tags in a repository
- Can include commit history for each ref
- Can specify the current HEAD

Example:
  <info>%command.full_name% --id my-project \
    --refs-heads-main abc123 \
    --refs-tags-v1.0 def456 \
    --head main</info>

Required:
- Repository ID (--id): Must match the identifier in the repository announcement

Optional:
- Branch/Tag References: Use --refs-heads-<branch> or --refs-tags-<tag> to specify commit IDs
- HEAD: Use --head to specify the current branch
- Commit History: Add parent commit IDs to track history

Note: If no refs are provided, this indicates that the author is no longer tracking repository state.
HELP
            )
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Repository ID (must match announcement)')
            ->addOption('head', null, InputOption::VALUE_REQUIRED, 'Current HEAD branch')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $relay = $input->getOption('relay') ?? 'wss://relay.damus.io';
        $json = $input->getOption('json');

        $id = $input->getOption('id');
        if (!$id) {
            $io->error('Repository ID is required');
            return Command::FAILURE;
        }

        try {
            $tags = [['d', $id]];

            // Add HEAD if specified
            if ($head = $input->getOption('head')) {
                $tags[] = ['HEAD', 'ref: refs/heads/' . $head];
            }

            // Add all refs from options
            foreach ($input->getOptions() as $key => $value) {
                if (str_starts_with($key, 'refs-heads-') || str_starts_with($key, 'refs-tags-')) {
                    $ref = str_replace(['refs-heads-', 'refs-tags-'], ['refs/heads/', 'refs/tags/'], $key);
                    if (is_array($value)) {
                        $tags[] = array_merge(['refs', $ref], $value);
                    } else {
                        $tags[] = ['refs', $ref, $value];
                    }
                }
            }

            $event = $this->nostrService->createEvent(
                kind: 30618,
                content: '',
                tags: $tags
            );

            $this->nostrService->publishEvent($event, $relay);
            $this->logger->info('Published git repository state', [
                'event_id' => $event->getId(),
                'repo_id' => $id,
                'relay' => $relay,
            ]);

            if ($json) {
                $io->writeln(json_encode($event, JSON_PRETTY_PRINT));
            } else {
                $io->success('Git repository state announced successfully');
                $io->writeln('Event ID: ' . $event->getId());
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to announce git repository state', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to announce git repository state: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
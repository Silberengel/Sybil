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
    name: 'ngit-status',
    description: 'Get git repository status (kind 30620)',
)]
class NgitStatusCommand extends Command
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
The <info>%command.name%</info> command gets the status of a git repository using kind 30620.

This command follows NIP-34 for git repository status. It creates an event that:
- Gets the current status of a repository
- Can filter by branch, tag, or commit
- Returns information about the repository state

Example:
  <info>%command.full_name% --id my-project \
    --branch main \
    --relay wss://relay.damus.io</info>

Required:
- Repository ID (--id): Must match the identifier in the repository announcement

Optional:
- Branch (--branch): Get status for a specific branch
- Tag (--tag): Get status for a specific tag
- Commit (--commit): Get status for a specific commit
- Relay (--relay): Specify relay URL to query
- JSON Output (--json): Output event in JSON format

Note: If no filters are provided, the overall repository status will be returned.
HELP
            )
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Repository ID')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch to get status for')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Tag to get status for')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'Commit to get status for')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to query')
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

            if ($branch = $input->getOption('branch')) {
                $tags[] = ['branch', $branch];
            }

            if ($tag = $input->getOption('tag')) {
                $tags[] = ['tag', $tag];
            }

            if ($commit = $input->getOption('commit')) {
                $tags[] = ['commit', $commit];
            }

            $event = $this->nostrService->createEvent(
                kind: 30620,
                content: '',
                tags: $tags
            );

            $this->nostrService->publishEvent($event, $relay);
            $this->logger->info('Published git repository status request', [
                'event_id' => $event->getId(),
                'repo_id' => $id,
                'relay' => $relay,
            ]);

            if ($json) {
                $io->writeln(json_encode($event, JSON_PRETTY_PRINT));
            } else {
                $io->success('Git repository status request sent successfully');
                $io->writeln('Event ID: ' . $event->getId());
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get git repository status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to get git repository status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
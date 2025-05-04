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
    name: 'ngit-feed',
    description: 'Subscribe to git repository feed (kind 30619)',
)]
class NgitFeedCommand extends Command
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
The <info>%command.name%</info> command subscribes to a git repository feed using kind 30619.

This command follows NIP-34 for git repository feeds. It creates an event that:
- Subscribes to updates from a specific repository
- Can filter by branch, tag, or commit
- Can specify the type of updates to receive

Example:
  <info>%command.full_name% --id my-project \
    --branch main \
    --type commits \
    --relay wss://relay.damus.io</info>

Required:
- Repository ID (--id): Must match the identifier in the repository announcement

Optional:
- Branch (--branch): Filter updates for a specific branch
- Tag (--tag): Filter updates for a specific tag
- Commit (--commit): Filter updates for a specific commit
- Type (--type): Type of updates to receive (commits, patches, issues, all)
- Relay (--relay): Specify relay URL to subscribe to
- JSON Output (--json): Output event in JSON format

Note: If no filters are provided, all updates from the repository will be received.
HELP
            )
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Repository ID')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch to filter by')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Tag to filter by')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'Commit to filter by')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type of updates (commits, patches, issues, all)')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to subscribe to')
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

            if ($type = $input->getOption('type')) {
                $tags[] = ['type', $type];
            }

            $event = $this->nostrService->createEvent(
                kind: 30619,
                content: '',
                tags: $tags
            );

            $this->nostrService->publishEvent($event, $relay);
            $this->logger->info('Published git repository feed subscription', [
                'event_id' => $event->getId(),
                'repo_id' => $id,
                'relay' => $relay,
            ]);

            if ($json) {
                $io->writeln(json_encode($event, JSON_PRETTY_PRINT));
            } else {
                $io->success('Git repository feed subscription created successfully');
                $io->writeln('Event ID: ' . $event->getId());
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create git repository feed subscription', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to create git repository feed subscription: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
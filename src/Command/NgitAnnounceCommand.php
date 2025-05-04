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
    name: 'ngit-announce',
    description: 'Announce a git repository (kind 30617)',
)]
class NgitAnnounceCommand extends Command
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
The <info>%command.name%</info> command announces a git repository using kind 30617.

This command follows NIP-34 for git repository announcements. It creates an event that:
- Announces a new git repository
- Specifies the repository name and description
- Can include repository metadata like default branch and visibility

Example:
  <info>%command.full_name% --id my-project \
    --name "My Project" \
    --description "A cool project" \
    --default-branch main \
    --private</info>

Required:
- Repository ID (--id): Unique identifier for the repository
- Name (--name): Display name of the repository

Optional:
- Description (--description): Repository description
- Default Branch (--default-branch): Name of the default branch
- Private Flag (--private): Mark repository as private
- Relay (--relay): Specify relay URL to publish to
- JSON Output (--json): Output event in JSON format

Note: The repository ID should be unique and follow git repository naming conventions.
HELP
            )
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Repository ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Repository name')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Repository description')
            ->addOption('default-branch', null, InputOption::VALUE_REQUIRED, 'Default branch name')
            ->addOption('private', null, InputOption::VALUE_NONE, 'Mark repository as private')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $relay = $input->getOption('relay') ?? 'wss://relay.damus.io';
        $json = $input->getOption('json');

        $id = $input->getOption('id');
        $name = $input->getOption('name');

        if (!$id || !$name) {
            $io->error('Repository ID and name are required');
            return Command::FAILURE;
        }

        try {
            $tags = [
                ['d', $id],
                ['name', $name],
            ];

            if ($description = $input->getOption('description')) {
                $tags[] = ['description', $description];
            }

            if ($defaultBranch = $input->getOption('default-branch')) {
                $tags[] = ['default_branch', $defaultBranch];
            }

            if ($input->getOption('private')) {
                $tags[] = ['private', 'true'];
            }

            $event = $this->nostrService->createEvent(
                kind: 30617,
                content: '',
                tags: $tags
            );

            $this->nostrService->publishEvent($event, $relay);
            $this->logger->info('Published git repository announcement', [
                'event_id' => $event->getId(),
                'repo_id' => $id,
                'name' => $name,
                'relay' => $relay,
            ]);

            if ($json) {
                $io->writeln(json_encode($event, JSON_PRETTY_PRINT));
            } else {
                $io->success('Git repository announced successfully');
                $io->writeln('Event ID: ' . $event->getId());
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to announce git repository', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to announce git repository: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
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
    name: 'git-announce',
    description: 'Announce a git repository (kind 30617)',
)]
class GitAnnounceCommand extends Command
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
- Announces the existence of a git repository
- Specifies where to find and clone the repository
- Lists maintainers and relays for patches/issues
- Provides metadata like name, description, and tags

Example:
  <info>%command.full_name% --id my-project --name "My Project" --description "A cool project" \
    --web https://github.com/user/repo \
    --clone https://github.com/user/repo.git \
    --relays wss://relay1.example.com,wss://relay2.example.com \
    --maintainers npub1abc123def456,npub1xyz789uvw012 \
    --tags nostr,git</info>

Required:
- Repository ID (--id): A kebab-case short name for the repository
- At least one of: web URL, clone URL, or maintainers

Optional:
- Name: Human-readable project name
- Description: Brief project description
- Web URL: URL for browsing the repository
- Clone URL: URL for git-cloning
- Relays: List of relays to monitor for patches/issues
- Maintainers: List of maintainer pubkeys
- Tags: List of hashtags for the repository
- Earliest Unique Commit: Commit ID to identify the repository among forks
HELP
            )
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Repository ID (kebab-case short name)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Human-readable project name')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Brief project description')
            ->addOption('web', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'URL for browsing')
            ->addOption('clone', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'URL for git-cloning')
            ->addOption('relays', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Relays to monitor for patches/issues')
            ->addOption('maintainers', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Maintainer pubkeys')
            ->addOption('tags', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Hashtags for the repository')
            ->addOption('euc', null, InputOption::VALUE_REQUIRED, 'Earliest unique commit ID')
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

        $webUrls = $input->getOption('web');
        $cloneUrls = $input->getOption('clone');
        $maintainers = $input->getOption('maintainers');

        if (empty($webUrls) && empty($cloneUrls) && empty($maintainers)) {
            $io->error('At least one of web URL, clone URL, or maintainers is required');
            return Command::FAILURE;
        }

        try {
            $tags = [['d', $id]];

            if ($name = $input->getOption('name')) {
                $tags[] = ['name', $name];
            }

            if ($description = $input->getOption('description')) {
                $tags[] = ['description', $description];
            }

            foreach ($webUrls as $url) {
                $tags[] = ['web', $url];
            }

            foreach ($cloneUrls as $url) {
                $tags[] = ['clone', $url];
            }

            foreach ($input->getOption('relays') as $relayUrl) {
                $tags[] = ['relays', $relayUrl];
            }

            foreach ($maintainers as $maintainer) {
                $tags[] = ['maintainers', $maintainer];
            }

            foreach ($input->getOption('tags') as $tag) {
                $tags[] = ['t', $tag];
            }

            if ($euc = $input->getOption('euc')) {
                $tags[] = ['r', $euc, 'euc'];
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
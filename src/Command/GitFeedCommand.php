<?php

namespace App\Command;

use App\Command\Trait\FeedCommandTrait;
use App\Service\NostrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'git-feed',
    description: 'Display git-related events in a threaded format',
)]
class GitFeedCommand extends Command
{
    use FeedCommandTrait;

    public function __construct(
        private readonly NostrService $nostrService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to query')
            ->addOption('repo-id', null, InputOption::VALUE_REQUIRED, 'Filter by repository ID')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Filter by repository owner')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of events to show', 20)
            ->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Show events since timestamp')
            ->addOption('until', 'u', InputOption::VALUE_REQUIRED, 'Show events until timestamp')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Show detailed information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $relay = $input->getOption('relay') ?? 'wss://relay.damus.io';
        $limit = (int) $input->getOption('limit');
        $repoId = $input->getOption('repo-id');
        $owner = $input->getOption('owner');
        $json = $input->getOption('json');
        $verbose = $input->getOption('verbose');

        $this->logger->info('Fetching git feed', [
            'relay' => $relay,
            'limit' => $limit,
            'repo_id' => $repoId,
            'owner' => $owner,
        ]);

        // Query for git-related events
        $filters = [
            'kinds' => [30617, 30618, 1617, 1621, 1630, 1631, 1632, 1633, 1111], // All git-related kinds plus replies
            'limit' => $limit,
        ];

        if ($repoId) {
            $filters['#d'] = [$repoId];
        }

        if ($owner) {
            $filters['authors'] = [$owner];
        }

        if ($since = $input->getOption('since')) {
            $filters['since'] = (int) $since;
        }

        if ($until = $input->getOption('until')) {
            $filters['until'] = (int) $until;
        }

        try {
            $events = $this->nostrService->queryEvents($relay, $filters);
            $this->logger->debug('Retrieved events', ['count' => count($events)]);

            $threads = $this->organizeThreads($events, $this->nostrService, $relay, $this->logger);
            $this->displayThreads($io, $threads, $this->nostrService, $relay, $json, $verbose);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch git feed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to fetch git feed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getKindName(int $kind): string
    {
        return match ($kind) {
            30617 => 'Repository',
            30618 => 'State',
            1617 => 'Patch',
            1621 => 'Issue',
            1630 => 'Open',
            1631 => 'Applied/Merged',
            1632 => 'Closed',
            1633 => 'Draft',
            1111 => 'Reply',
            default => 'Unknown',
        };
    }
} 
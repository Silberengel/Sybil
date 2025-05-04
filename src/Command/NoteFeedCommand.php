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
    name: 'note-feed',
    description: 'Display kind 01 notes with their replies',
)]
class NoteFeedCommand extends Command
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
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Filter by author')
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
        $author = $input->getOption('author');
        $json = $input->getOption('json');
        $verbose = $input->getOption('verbose');

        $this->logger->info('Fetching note feed', [
            'relay' => $relay,
            'limit' => $limit,
            'author' => $author,
        ]);

        // Query for kind 01 notes and their replies
        $filters = [
            'kinds' => [1, 1111], // Kind 01 notes and replies
            'limit' => $limit,
        ];

        if ($author) {
            $filters['authors'] = [$author];
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
            $this->logger->error('Failed to fetch note feed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to fetch note feed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getKindName(int $kind): string
    {
        return match ($kind) {
            1 => 'Note',
            1111 => 'Reply',
            default => 'Unknown',
        };
    }
} 
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
    name: 'publication-feed',
    description: 'Display publication content (kinds 30040, 30041) with their replies and highlights',
)]
class PublicationFeedCommand extends Command
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
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command displays publication content and their interactions.

Publication content (kind 30041) events can be part of one or more publications (kind 30040).
The feed shows up to 3 most recent publications for each content event, including:
- Title
- Author
- Event ID
- Creation timestamp

For standalone content events, they are marked as such.

The feed also includes:
- Replies to both types (kind 1111)
- Highlights of both types (kind 9802)

Example:
  <info>%command.full_name% --type content --author npub1abc123</info>
  <info>%command.full_name% --identifier my-publication --type all</info>
  <info>%command.full_name% --verbose --raw</info>
HELP
            )
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to query')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Filter by author')
            ->addOption('identifier', 'i', InputOption::VALUE_REQUIRED, 'Filter by publication identifier')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by type (index|content|all)', 'all')
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
        $identifier = $input->getOption('identifier');
        $type = $input->getOption('type');
        $json = $input->getOption('json');
        $verbose = $input->getOption('verbose');

        $this->logger->info('Fetching publication feed', [
            'relay' => $relay,
            'limit' => $limit,
            'author' => $author,
            'identifier' => $identifier,
            'type' => $type,
        ]);

        // Query for publication content and their replies/highlights
        $kinds = match ($type) {
            'index' => [30040],
            'content' => [30041],
            default => [30040, 30041],
        };

        $filters = [
            'kinds' => [...$kinds, 1111, 9802], // Publication content, replies, and highlights
            'limit' => $limit,
        ];

        if ($author) {
            $filters['authors'] = [$author];
        }

        if ($identifier) {
            $filters['#d'] = [$identifier];
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

            // If we have 30041 events, fetch their parent 30040 events
            $contentEvents = array_filter($events, fn($e) => $e->getKind() === 30041);
            if (!empty($contentEvents)) {
                $parentIdentifiers = array_map(function($event) {
                    $dTag = $event->getTag('d');
                    return $dTag ? $dTag[1] : null;
                }, $contentEvents);
                $parentIdentifiers = array_filter($parentIdentifiers);

                if (!empty($parentIdentifiers)) {
                    $parentFilters = [
                        'kinds' => [30040],
                        '#d' => array_values($parentIdentifiers),
                    ];
                    if ($author) {
                        $parentFilters['authors'] = [$author];
                    }
                    $parentEvents = $this->nostrService->queryEvents($relay, $parentFilters);
                    $events = array_merge($events, $parentEvents);
                }
            }

            $threads = $this->organizeThreads($events, $this->nostrService, $relay, $this->logger);
            $this->displayThreads($io, $threads, $this->nostrService, $relay, $json, $verbose);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch publication feed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to fetch publication feed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getKindName(int $kind): string
    {
        return match ($kind) {
            30040 => 'Publication Index',
            30041 => 'Publication Content',
            1111 => 'Reply',
            9802 => 'Highlight',
            default => 'Unknown',
        };
    }

    private function getEventTitle(\Nostr\Event $event): string
    {
        if ($event->getKind() === 30041) {
            $dTag = $event->getTag('d');
            if ($dTag) {
                return "Content for publication: {$dTag[1]}";
            }
        }
        return parent::getEventTitle($event);
    }

    private function getEventContent(\Nostr\Event $event, array $allEvents): string
    {
        $content = parent::getEventContent($event, $allEvents);

        // For 30041 events, add parent 30040 information if available
        if ($event->getKind() === 30041) {
            $dTag = $event->getTag('d');
            if ($dTag) {
                $parentEvents = array_filter($allEvents, function($e) use ($dTag) {
                    return $e->getKind() === 30040 && $e->getTag('d') && $e->getTag('d')[1] === $dTag[1];
                });

                if (!empty($parentEvents)) {
                    // Sort parent events by creation time (newest first)
                    usort($parentEvents, function($a, $b) {
                        return $b->getCreatedAt() <=> $a->getCreatedAt();
                    });

                    $totalParents = count($parentEvents);
                    $displayParents = array_slice($parentEvents, 0, 3);
                    
                    $content = "Part of " . $totalParents . " publication" . ($totalParents > 1 ? "s" : "") . 
                             ($totalParents > 3 ? " (showing 3 most recent)" : "") . ":\n\n";
                    
                    foreach ($displayParents as $index => $parentEvent) {
                        $parentAuthor = $this->nostrService->getAuthorName($parentEvent->getPubkey(), $allEvents);
                        $content .= sprintf(
                            "%sPublication %d:\nTitle: %s\nAuthor: %s\nEvent ID: %s\nCreated: %s\n\n",
                            $index > 0 ? "\n" : "",
                            $index + 1,
                            $this->getEventTitle($parentEvent),
                            $parentAuthor,
                            $parentEvent->getId(),
                            date('Y-m-d H:i:s', $parentEvent->getCreatedAt())
                        );
                    }

                    $content .= "Content:\n" . $content;
                } else {
                    $content = "Standalone publication content\n\n" . $content;
                }
            }
        }

        return $content;
    }
} 
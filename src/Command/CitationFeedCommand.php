<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for viewing citation feeds
 * 
 * This command handles the 'citation:feed' command, which displays
 * citation events (kinds 30-33) in a feed format.
 * 
 * Usage: sybil citation:feed [--relay <url>] [--author <pubkey>] [--limit <number>] [--raw] [--verbose]
 */
class CitationFeedCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;
    use CommandImportsTrait;

    private NostrEventService $eventService;
    private LoggerInterface $logger;

    public function __construct(
        NostrEventService $eventService,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->eventService = $eventService;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'citation:feed';
    }

    public function getDescription(): string
    {
        return 'Display a feed of citation events (kinds 30-33)';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays citation events (kinds 30-33) in a feed format.

This command follows NKBIP-03 for citations. It displays:
- Internal references (kind 30)
- Web references (kind 31)
- Hardcopy references (kind 32)
- Prompt references (kind 33)

Each citation shows:
- Display type (how it's referenced in articles)
- Citation type (internal, web, hardcopy, prompt)
- Title and author
- Creation timestamp
- Event ID (with --verbose)

Example:
  <info>%command.full_name% --relay wss://relay.example.com --limit 10</info>

Options:
- Relay (--relay): Relay URL to fetch from
- Limit (--limit): Maximum number of events to display (default: 10)
- Verbose (-v): Show additional event details

The feed displays:
- Citation events (kinds 30-33)
- How each citation is referenced in articles:
  - Endnotes ([[citation::end::nevent...]])
  - Footnotes ([[citation::foot::nevent...]])
  - Footnotes with endnotes ([[citation::foot-end::nevent...]])
  - Inline references ([[citation::inline::nevent...]])
  - Quoted content ([[citation::quote::nevent...]])
  - AI citations ([[citation::prompt-end::nevent...]] or [[citation::prompt-inline::nevent...]])
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('citation:feed')
            ->setDescription('Display a feed of citation events')
            ->addOption('relay', null, InputOption::VALUE_REQUIRED, 'The relay URL to fetch from')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of events to display', 10)
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Show additional event details');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $relay = $input->getOption('relay');
            $limit = (int) $input->getOption('limit');
            $verbose = $input->getOption('verbose');

            // Get events from relay
            $events = $this->eventService->getEvents($relay, [
                'kinds' => [30, 31, 32, 33],
                'limit' => $limit
            ]);

            if (empty($events)) {
                $output->writeln('<info>No citation events found.</info>');
                return Command::SUCCESS;
            }

            // Get parent articles that reference these citations
            $citationIds = array_map(fn($e) => $e->getId(), $events);
            $parentEvents = $this->eventService->getEvents($relay, [
                'kinds' => [30023, 30040, 30041, 30050], // longform, publication, section, wiki
                'limit' => 100 // Get more to ensure we find references
            ]);

            // Map citations to their display types based on parent article references
            $citationDisplayTypes = [];
            foreach ($parentEvents as $parentEvent) {
                $content = $parentEvent->getContent();
                foreach ($citationIds as $citationId) {
                    if (preg_match('/\[\[citation::([^:]+)::' . preg_quote($citationId, '/') . '\]\]/', $content, $matches)) {
                        $citationDisplayTypes[$citationId] = $matches[1];
                    }
                }
            }

            // Display events
            foreach ($events as $event) {
                $displayType = $citationDisplayTypes[$event->getId()] ?? 'unknown';
                $displayTypeLabel = match ($displayType) {
                    'end' => 'Endnote',
                    'foot' => 'Footnote',
                    'foot-end' => 'Footnote (with endnote)',
                    'inline' => 'Inline Reference',
                    'quote' => 'Quoted Content',
                    'prompt-end' => 'AI Citation (Endnote)',
                    'prompt-inline' => 'AI Citation (Inline)',
                    default => 'Unknown Display Type'
                };

                $output->writeln(sprintf(
                    '<info>%s</info> [%s]',
                    $displayTypeLabel,
                    $this->getCitationTypeLabel($event->getKind())
                ));

                $output->writeln(sprintf(
                    'Author: %s',
                    $this->getAuthorName($event)
                ));

                $output->writeln(sprintf(
                    'Title: %s',
                    $this->getTitle($event)
                ));

                if ($verbose) {
                    $output->writeln(sprintf(
                        'Created: %s',
                        date('Y-m-d H:i:s', $event->getCreatedAt())
                    ));
                    $output->writeln(sprintf(
                        'Event ID: %s',
                        $event->getId()
                    ));
                }

                $output->writeln('');
            }

            return Command::SUCCESS;
        });
    }

    private function getCitationTypeLabel(int $kind): string
    {
        return match ($kind) {
            30 => 'Internal Reference',
            31 => 'Web Reference',
            32 => 'Hardcopy Reference',
            33 => 'Prompt Reference',
            default => 'Unknown Type'
        };
    }

    private function getAuthorName(NostrEvent $event): string
    {
        foreach ($event->getTags() as $tag) {
            if ($tag[0] === 'author') {
                return $tag[1];
            }
        }
        return 'Unknown Author';
    }

    private function getTitle(NostrEvent $event): string
    {
        foreach ($event->getTags() as $tag) {
            if ($tag[0] === 'title') {
                return $tag[1];
            }
        }
        return 'Untitled';
    }
} 
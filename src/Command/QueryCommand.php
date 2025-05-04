<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for querying Nostr relays for events
 * 
 * This command handles the 'query' command, which allows querying
 * Nostr relays for events with various filters. You can filter by
 * kind, author, tags, and time range. The --sync option saves events
 * to the local database, and --raw outputs in JSON format.
 * 
 * Usage: nostr:query -r <relay> [-k <kind>] [-a <author>] [-t <tag>] [-s <since>] [-u <until>] [-l <limit>] [--sync] [-j]
 * 
 * Examples:
 *   sybil query -r wss://relay.example.com -k 1
 *   sybil query -r wss://relay.example.com -a npub1... -t e:abc123
 *   sybil query -r wss://relay.example.com -k 1 -s 1234567890 -l 10 --raw
 *   sybil query -r wss://relay.example.com -k 1 --sync
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class QueryCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait; 
    use CommandImportsTrait;

    private NostrEventRepository $eventRepository;
    private RelayQueryService $relayQueryService;
    private LoggerInterface $logger;

    public function __construct(
        NostrEventRepository $eventRepository,
        RelayQueryService $relayQueryService,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->eventRepository = $eventRepository;
        $this->relayQueryService = $relayQueryService;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'nostr:query';
    }

    public function getDescription(): string
    {
        return 'Query Nostr relays for events';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command allows querying Nostr relays for events with various filters.

<info>php %command.full_name% -r RELAY_URL [-k KIND] [-a AUTHOR] [-t TAG] [-s SINCE] [-u UNTIL] [-l LIMIT] [--sync] [-j]</info>

Arguments:
  -r, --relay    The relay URL to query (required)
  -k, --kind     Filter by event kind
  -a, --author   Filter by author public key
  -t, --tag      Filter by tag (format: name:value)
  -s, --since    Filter by start timestamp
  -u, --until    Filter by end timestamp
  -l, --limit    Limit number of results
  --sync         Save events to local database
  -j, --raw     Output in JSON format

Examples:
  <info>php %command.full_name% -r wss://relay.example.com -k 1</info>
  <info>php %command.full_name% -r wss://relay.example.com -a npub1... -t e:abc123</info>
  <info>php %command.full_name% -r wss://relay.example.com -k 1 -s 1234567890 -l 10 --raw</info>
  <info>php %command.full_name% -r wss://relay.example.com -k 1 --sync</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('query')
            ->setDescription('Query Nostr relays for events')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'The relay URL to query')
            ->addOption('kind', 'k', InputOption::VALUE_REQUIRED, 'Filter by event kind')
            ->addOption('author', 'a', InputOption::VALUE_REQUIRED, 'Filter by author public key')
            ->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'Filter by tag (format: name:value)')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED, 'Filter by start timestamp')
            ->addOption('until', 'u', InputOption::VALUE_REQUIRED, 'Filter by end timestamp')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of results')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Save events to local database')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $relay = $input->getOption('relay');
            if (empty($relay)) {
                throw new \InvalidArgumentException('Relay URL is required');
            }

            $this->validateRelayUrlOrFail($relay);

            $filter = $this->buildFilter($input);
            $events = $this->relayQueryService->query($filter);

            if ($input->getOption('json')) {
                $output->writeln(json_encode($events, JSON_PRETTY_PRINT));
            } else {
                $this->formatQueryResults($events, $output);
            }

            if ($input->getOption('sync')) {
                $this->eventRepository->syncFromRelays($filter);
            }

            return Command::SUCCESS;
        });
    }

    private function buildFilter(InputInterface $input): array
    {
        $filter = [];
        
        if ($input->getOption('kind')) {
            $filter['kinds'] = [(int)$input->getOption('kind')];
        }
        
        if ($input->getOption('author')) {
            $filter['authors'] = [$input->getOption('author')];
        }
        
        if ($input->getOption('tag')) {
            $tag = $input->getOption('tag');
            if (!str_contains($tag, ':')) {
                throw new \InvalidArgumentException('Invalid tag format: ' . $tag);
            }
            [$tagName, $tagValue] = explode(':', $tag, 2);
            $filter['tags'] = [[$tagName, $tagValue]];
        }
        
        if ($input->getOption('since')) {
            $filter['since'] = (int)$input->getOption('since');
        }
        
        if ($input->getOption('until')) {
            $filter['until'] = (int)$input->getOption('until');
        }
        
        if ($input->getOption('limit')) {
            $filter['limit'] = (int)$input->getOption('limit');
        }

        return $filter;
    }

    private function formatQueryResults(array $events, OutputInterface $output): void
    {
        $output->writeln('Query Results');
        if (empty($events)) {
            $output->writeln('No events found');
            return;
        }

        $output->writeln('Found ' . count($events) . ' events:');
        foreach ($events as $event) {
            $output->writeln('');
            $output->writeln('Event ' . $event->getId());
            $output->writeln('Kind: ' . $event->getKind());
            $output->writeln('Author: ' . $event->getPubkey());
            $output->writeln('Created: ' . date('Y-m-d H:i:s', $event->getCreatedAt()));
            $output->writeln('Content: ' . $event->getContent());
            $output->writeln('Tags: ' . json_encode($event->getTags()));
        }
    }
} 
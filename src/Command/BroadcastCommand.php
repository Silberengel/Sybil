<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for broadcasting a Nostr event to multiple relays
 * 
 * This command handles the 'broadcast' command, which broadcasts a Nostr event
 * to multiple relays.
 * Usage: nostr:broadcast <event_json> [--relays <relay_urls>]
 */
class BroadcastCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;
    use CommandImportsTrait;

    private NostrEventService $eventService;
    private LoggerInterface $logger;
    private string $privateKey;

    public function __construct(
        NostrEventService $eventService,
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        parent::__construct();
        $this->eventService = $eventService;
        $this->logger = $logger;
        $this->privateKey = $params->get('app.private_key');
    }

    public function getName(): string
    {
        return 'nostr:broadcast';
    }

    public function getDescription(): string
    {
        return 'Broadcast a Nostr event to multiple relays';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command broadcasts a Nostr event to multiple relays.

<info>php %command.full_name% <event_json> [--relays <relay_urls>] [--json]</info>

The <event_json> argument can be:
  - A JSON string containing the event data
  - A path to a JSON file
  - "-" to read from stdin

Options:
  --relays (-r)    Comma-separated list of relay URLs to broadcast to
  --json           Output raw event data in JSON format

Examples:
  <info>php %command.full_name% '{"kind":1,"content":"Hello"}'</info>
  <info>php %command.full_name% event.json --relays wss://relay1.com,wss://relay2.com</info>
  <info>cat event.json | php %command.full_name% -</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('broadcast')
            ->setDescription('Broadcast a Nostr event to multiple relays')
            ->addArgument('event_json', InputArgument::REQUIRED, 'The event JSON data or path to JSON file')
            ->addOption('relays', 'r', InputOption::VALUE_REQUIRED, 'Comma-separated list of relay URLs')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output raw event data');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $eventJson = $input->getArgument('event_json');
            $relays = $input->getOption('relays') ? explode(',', $input->getOption('relays')) : [];
            $raw = $input->getOption('raw');

            if (!$eventJson) {
                throw new RuntimeException('Event JSON data is required');
            }

            // Get event data from JSON
            $eventData = $this->getEventData($eventJson);
            if ($eventData === null) {
                throw new RuntimeException('Invalid event JSON data');
            }

            // Validate relay URLs if provided
            if (!empty($relays)) {
                $invalidRelays = $this->validateRelayUrls($relays);
                if (!empty($invalidRelays)) {
                    throw new RuntimeException('Invalid relay URLs: ' . implode(', ', $invalidRelays));
                }
            }

            // Get public key for authentication
            $publicKey = $this->getPublicKey();
            
            // Create event
            $event = $this->eventService->createEvent($eventData);

            // Broadcast event with authentication
            $results = $this->eventService->broadcastEvent($event, $relays, $publicKey);

            if ($raw) {
                $output->writeln(json_encode([
                    'event' => $event->toArray(),
                    'results' => $results,
                ], JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Event broadcasted successfully with ID: %s</info>', $event->getId()));
                
                $output->writeln('<info>Broadcast Results:</info>');
                foreach ($results as $relay => $success) {
                    $output->writeln(sprintf('  %s: %s', $relay, $success ? '✓' : '✗'));
                }
            }

            return Command::SUCCESS;
        });
    }

    private function getEventData(string $input): ?array
    {
        try {
            // Handle stdin
            if ($input === '-') {
                $input = file_get_contents('php://stdin');
                if ($input === false) {
                    throw new RuntimeException('Failed to read from stdin');
                }
            }

            // Handle file path
            if (file_exists($input)) {
                $input = file_get_contents($input);
                if ($input === false) {
                    throw new RuntimeException('Failed to read file');
                }
            }

            // Parse JSON
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
            }

            // Validate required fields
            $requiredFields = ['id', 'pubkey', 'created_at', 'kind', 'content', 'tags', 'sig'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new RuntimeException('Missing required field: ' . $field);
                }
            }

            return $data;
        } catch (\Exception $e) {
            throw new RuntimeException('Error processing event data: ' . $e->getMessage());
        }
    }

    private function validateRelayUrls(array $relays): array
    {
        $invalidRelays = [];
        foreach ($relays as $relay) {
            if (!filter_var($relay, FILTER_VALIDATE_URL) || !preg_match('/^wss?:\/\//', $relay)) {
                $invalidRelays[] = $relay;
            }
        }
        return $invalidRelays;
    }

    private function getPublicKey(): string
    {
        try {
            $keyPair = new NostrKeyPair($this->privateKey);
            return $keyPair->getPublicKey();
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to derive public key: ' . $e->getMessage());
        }
    }
} 
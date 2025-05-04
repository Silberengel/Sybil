<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for republishing a Nostr event
 * 
 * This command handles the 'republish' command, which republishes a Nostr event
 * by providing its JSON data. The event must be a valid Nostr event in JSON format.
 * The --raw option outputs the republished event in JSON format.
 * 
 * Usage: nostr:republish <event_json> [--relay <relay_url>] [--raw]
 * 
 * Examples:
 *   sybil republish '{"kind":1,"content":"Hello","created_at":1234567890,"tags":[]}'
 *   sybil republish '{"kind":1,"content":"Hello"}' --relay wss://relay.example.com
 *   sybil republish '{"kind":1,"content":"Hello"}' --raw
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class RepublishCommand extends Command implements CommandInterface
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
        return 'nostr:republish';
    }

    public function getDescription(): string
    {
        return 'Republish a Nostr event by providing its JSON data';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command republishes a Nostr event by providing its JSON data.

<info>php %command.full_name% <event_json> [--relay <relay_url>] [--raw]</info>

Arguments:
  event_json  The JSON data of the event to republish (or '-' for stdin, or a file path)

Options:
  --relay     The relay URL to publish to (optional)
  --raw      Output in JSON format

Examples:
  <info>php %command.full_name% '{"kind":1,"content":"Hello","created_at":1234567890,"tags":[]}'</info>
  <info>php %command.full_name% '{"kind":1,"content":"Hello"}' --relay wss://relay.example.com</info>
  <info>php %command.full_name% '{"kind":1,"content":"Hello"}' --raw</info>
  <info>cat event.json | php %command.full_name% -</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('republish')
            ->setDescription('Republish a Nostr event by providing its JSON data')
            ->addArgument('event_json', InputArgument::REQUIRED, 'The JSON data of the event to republish (or "-" for stdin, or a file path)')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'The relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $eventJson = $input->getArgument('event_json');
            $relay = $input->getOption('relay');
            $jsonOutput = $input->getOption('json');

            // Get event data from JSON
            $eventData = $this->getEventData($eventJson);
            if ($eventData === null) {
                throw new \RuntimeException('Invalid event JSON data');
            }

            // Validate event data
            if (!EventUtility::isValidEvent($eventData)) {
                throw new \RuntimeException('Invalid event data');
            }

            // Create and publish event
            $event = $this->eventService->createEvent($eventData);
            $success = $this->eventService->publishEvent($event);

            if (!$success) {
                throw new \RuntimeException('Failed to republish event');
            }

            if ($jsonOutput) {
                $output->writeln(json_encode($event->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Event republished successfully with ID: %s</info>', $event->getId()));
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
                    throw new \RuntimeException('Failed to read from stdin');
                }
            }

            // Handle file path
            if (file_exists($input)) {
                $input = file_get_contents($input);
                if ($input === false) {
                    throw new \RuntimeException('Failed to read file');
                }
            }

            // Parse JSON
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON data: ' . json_last_error_msg());
            }

            // Validate required fields
            $requiredFields = ['id', 'pubkey', 'created_at', 'kind', 'content', 'tags', 'sig'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new \RuntimeException('Missing required field: ' . $field);
                }
            }

            return $data;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error parsing event data: ' . $e->getMessage());
        }
    }
} 
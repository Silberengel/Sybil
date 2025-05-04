<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for adding a new Nostr relay
 * 
 * This command handles the 'relay-add' command, which adds a new
 * Nostr relay to the list of known relays. The command can optionally
 * test the relay before adding it.
 * 
 * Usage: nostr:relay-add <relay> [--test] [--raw]
 * 
 * Examples:
 *   sybil relay-add wss://relay.example.com
 *   sybil relay-add wss://relay.example.com --test
 *   sybil relay-add wss://relay.example.com --test --raw
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class RelayAddCommand extends Command implements CommandInterface
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
        return 'nostr:relay-add';
    }

    public function getDescription(): string
    {
        return 'Add a new Nostr relay';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command adds a new Nostr relay to the list of known relays.

<info>php %command.full_name% RELAY_URL [--test] [--raw]</info>

Arguments:
  RELAY_URL    The WebSocket URL of the relay to add (required)

Options:
  --test       Test the relay before adding it
  --raw       Output raw event data

Examples:
  <info>php %command.full_name% wss://relay.example.com</info>
  <info>php %command.full_name% wss://relay.example.com --test</info>
  <info>php %command.full_name% wss://relay.example.com --test --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('relay-add')
            ->setDescription('Add a new Nostr relay')
            ->addArgument('relay', InputArgument::REQUIRED, 'The WebSocket URL of the relay to add')
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test the relay before adding it')
            ->addOption('raw', 'r', InputOption::VALUE_NONE, 'Output raw event data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $relayUrl = $input->getArgument('relay');
            $raw = $input->getOption('raw');
            $test = $input->getOption('test');

            $this->validateRelayUrlOrFail($relayUrl);

            // Check if relay already exists
            if (RelayUtility::isKnownRelay($relayUrl)) {
                throw new CommandException(
                    'Relay already exists in the list',
                    CommandException::DUPLICATE_ENTRY,
                    ['relay' => $relayUrl]
                );
            }

            // Test relay if requested
            if ($test) {
                $testResults = $this->testRelay($relayUrl);
                
                if (!$testResults['success']) {
                    throw new CommandException(
                        $testResults['error'] ?? 'Unknown error',
                        CommandException::TEST_FAILED,
                        ['relay' => $relayUrl]
                    );
                }

                if ($raw) {
                    $output->writeln(json_encode($testResults, JSON_PRETTY_PRINT));
                } else {
                    $this->formatRelayTestResults($testResults, $output);
                }
            }

            // Add relay
            $success = RelayUtility::addRelay($relayUrl);
            
            if (!$success) {
                throw new CommandException(
                    'Failed to add relay',
                    CommandException::OPERATION_FAILED,
                    ['relay' => $relayUrl]
                );
            }

            if ($raw) {
                $output->writeln(json_encode([
                    'success' => true,
                    'relay' => $relayUrl,
                    'message' => 'Relay added successfully'
                ], JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Relay %s added successfully</info>', $relayUrl));
            }

            return Command::SUCCESS;
        });
    }

    private function testRelay(string $relayUrl): array
    {
        $start = microtime(true);
        $success = false;
        $error = null;
        $info = null;

        try {
            // Test basic connectivity
            $connectivity = $this->testRelayConnectivity($relayUrl);
            if (!$connectivity['status']) {
                throw new \Exception($connectivity['message']);
            }

            // Get relay info
            $info = RelayUtility::getRelayInfo($relayUrl);
            $success = $info !== null;

            $this->logger->debug('Relay info retrieved', [
                'relay' => $relayUrl,
                'success' => $success,
                'has_info' => $info !== null
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Relay test failed', [
                'relay' => $relayUrl,
                'error' => $e->getMessage()
            ]);
            $error = $e->getMessage();
        }

        return [
            'relay' => $relayUrl,
            'success' => $success,
            'error' => $error,
            'info' => $info,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];
    }
} 
<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for testing Nostr relay connectivity and functionality
 * 
 * This command handles the 'relay-test' command, which tests
 * the connectivity and functionality of a Nostr relay.
 * 
 * Usage: nostr:relay-test <relay> [--json]
 * 
 * Examples:
 *   sybil relay-test wss://relay.example.com
 *   sybil relay-test wss://relay.example.com --json
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class RelayTestCommand extends Command implements CommandInterface
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
        return 'nostr:relay-test';
    }

    public function getDescription(): string
    {
        return 'Test the connectivity and functionality of a Nostr relay';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command tests the connectivity and functionality of a Nostr relay.

<info>php %command.full_name% <relay> [--json]</info>

Arguments:
  relay       The URL of the relay to test (must start with ws:// or wss://)

Options:
  --json      Output in JSON format

Examples:
  <info>php %command.full_name% wss://relay.example.com</info>
  <info>php %command.full_name% wss://relay.example.com --json</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('relay-test')
            ->setDescription('Test the connectivity and functionality of a Nostr relay')
            ->addArgument('relay', InputArgument::REQUIRED, 'The URL of the relay to test')
            ->addOption('protocol', 'p', InputOption::VALUE_REQUIRED, 'Protocol to use (ws or http)', 'ws')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $relayUrl = $input->getArgument('relay');
            $protocol = $input->getOption('protocol');
            $jsonOutput = $input->getOption('json');

            if (!in_array($protocol, ['ws', 'http'])) {
                throw new RuntimeException('Invalid protocol. Must be either "ws" or "http"');
            }

            $this->validateRelayUrlOrFail($relayUrl, $protocol);

            $results = $this->runTests($relayUrl, $protocol);
            
            if ($jsonOutput) {
                $output->writeln(json_encode($results, JSON_PRETTY_PRINT));
            } else {
                $this->formatRelayTestResults($results, $output);
            }

            return $results['overall_status'] ? Command::SUCCESS : Command::FAILURE;
        });
    }

    private function runTests(string $relayUrl, string $protocol): array
    {
        $results = [
            'relay_url' => $relayUrl,
            'protocol' => $protocol,
            'tests' => [],
            'overall_status' => true,
            'start_time' => microtime(true),
        ];

        // Test 1: Basic Connectivity
        $results['tests']['connectivity'] = $this->testRelayConnectivity($relayUrl, $protocol);
        $results['overall_status'] &= $results['tests']['connectivity']['status'];

        // Test 2: NIP-11 Information
        $results['tests']['nip11'] = $this->getRelayInfo($relayUrl, $protocol);
        $results['overall_status'] &= $results['tests']['nip11']['status'];

        // Test 3: Event Publishing
        $results['tests']['publishing'] = $this->testEventPublishing($relayUrl, $protocol);
        $results['overall_status'] &= $results['tests']['publishing']['status'];

        // Test 4: Event Querying
        $results['tests']['querying'] = $this->testEventQuerying($relayUrl, $protocol);
        $results['overall_status'] &= $results['tests']['querying']['status'];

        $results['end_time'] = microtime(true);
        $results['duration'] = round($results['end_time'] - $results['start_time'], 2);

        return $results;
    }

    private function formatRelayTestResults(array $results, OutputInterface $output): void
    {
        $output->writeln(sprintf('<info>Testing relay: %s (Protocol: %s)</info>', $results['relay_url'], $results['protocol']));
        $output->writeln('');

        foreach ($results['tests'] as $test) {
            $status = $test['status'] ? '<success>✓</success>' : '<error>✗</error>';
            $output->writeln(sprintf(
                '%s %s (%s seconds)',
                $status,
                $test['name'],
                $test['duration']
            ));
            $output->writeln(sprintf('  %s', $test['message']));

            if (isset($test['info'])) {
                $output->writeln('  NIP-11 Information:');
                $output->writeln(sprintf('    Name: %s', $test['info']['name'] ?? 'Unknown'));
                $output->writeln(sprintf('    Description: %s', $test['info']['description'] ?? 'No description'));
                $output->writeln(sprintf('    Software: %s', $test['info']['software'] ?? 'Unknown'));
                $output->writeln(sprintf('    Version: %s', $test['info']['version'] ?? 'Unknown'));
            }

            if (isset($test['events'])) {
                $output->writeln(sprintf('  Retrieved %d events', count($test['events'])));
            }

            $output->writeln('');
        }

        $overallStatus = $results['overall_status'] ? '<success>PASSED</success>' : '<error>FAILED</error>';
        $output->writeln(sprintf(
            '<info>Overall Status: %s (Completed in %s seconds)</info>',
            $overallStatus,
            $results['duration']
        ));
    }
} 
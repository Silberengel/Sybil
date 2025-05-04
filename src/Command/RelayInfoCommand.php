<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for displaying information about a Nostr relay
 * 
 * This command handles the 'relay-info' command, which displays
 * information about a Nostr relay.
 * 
 * Usage: nostr:relay-info <relay> [--json]
 * 
 * Examples:
 *   sybil relay-info wss://relay.example.com
 *   sybil relay-info wss://relay.example.com --json
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class RelayInfoCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;
    use CommandImportsTrait;
    private RelayQueryService $relayQueryService;
    private LoggerInterface $logger;

    public function __construct(
        RelayQueryService $relayQueryService,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->relayQueryService = $relayQueryService;
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'nostr:relay-info';
    }

    public function getDescription(): string
    {
        return 'Get information about a Nostr relay';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays information about a Nostr relay.

<info>php %command.full_name% RELAY_URL [--json]</info>

Arguments:
  RELAY_URL    The WebSocket URL of the relay to query (required)

Options:
  --json       Output in JSON format

Examples:
  <info>php %command.full_name% wss://relay.example.com</info>
  <info>php %command.full_name% wss://relay.example.com --json</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('relay-info')
            ->setDescription('Get information about a Nostr relay')
            ->addArgument('relay', InputArgument::REQUIRED, 'The relay URL to query')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $relayUrl = $input->getArgument('relay');
            $jsonOutput = $input->getOption('json');

            // Validate relay URL
            $this->validateRelayUrlOrFail($relayUrl);

            // Get relay info
            $info = $this->relayQueryService->getRelayInfo($relayUrl);

            if ($jsonOutput) {
                $output->writeln(json_encode($info, JSON_PRETTY_PRINT));
            } else {
                $this->formatRelayInfo($info, $relayUrl, $output);
            }

            return Command::SUCCESS;
        });
    }

    private function formatRelayInfo(array $info, string $relayUrl, OutputInterface $output): void
    {
        $output->writeln(sprintf('<info>Relay: %s</info>', $relayUrl));
        $output->writeln(sprintf('Name: %s', $info['name'] ?? 'Unknown'));
        $output->writeln(sprintf('Description: %s', $info['description'] ?? 'None'));
        $output->writeln(sprintf('Pubkey: %s', $info['pubkey'] ?? 'None'));
        $output->writeln(sprintf('Contact: %s', $info['contact'] ?? 'None'));
        $output->writeln(sprintf('Software: %s', $info['software'] ?? 'Unknown'));
        $output->writeln(sprintf('Version: %s', $info['version'] ?? 'Unknown'));
    }
} 
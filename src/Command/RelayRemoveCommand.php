<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for removing a Nostr relay
 * 
 * This command handles the 'relay-remove' command, which removes a
 * Nostr relay from the list of known relays. The --force option can
 * be used to remove a relay even if it's still active.
 * 
 * Usage: nostr:relay-remove <relay> [--force] [--json]
 * 
 * Examples:
 *   sybil relay-remove wss://relay.example.com
 *   sybil relay-remove wss://relay.example.com --force
 *   sybil relay-remove wss://relay.example.com --json
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class RelayRemoveCommand extends Command implements CommandInterface
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
        return 'nostr:relay-remove';
    }

    public function getDescription(): string
    {
        return 'Remove a Nostr relay';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command removes a Nostr relay from the list of known relays.

<info>php %command.full_name% <relay> [--force] [--json]</info>

Arguments:
  relay       The URL of the relay to remove (must start with ws:// or wss://)

Options:
  --force     Remove the relay without confirmation
  --json      Output in JSON format

Examples:
  <info>php %command.full_name% wss://relay.example.com</info>
  <info>php %command.full_name% wss://relay.example.com --force</info>
  <info>php %command.full_name% wss://relay.example.com --json</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('relay-remove')
            ->setDescription('Remove a Nostr relay')
            ->addArgument('relay', InputArgument::REQUIRED, 'The URL of the relay to remove')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Remove the relay without confirmation')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $relayUrl = $input->getArgument('relay');
            $force = $input->getOption('force');
            $jsonOutput = $input->getOption('json');

            $this->validateRelayUrlOrFail($relayUrl);

            // Check if relay exists
            if (!RelayUtility::isKnownRelay($relayUrl)) {
                throw new CommandException(
                    'Relay not found in the list',
                    CommandException::NOT_FOUND,
                    ['relay' => $relayUrl]
                );
            }

            // Confirm removal unless --force is used
            if (!$force) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    sprintf('Are you sure you want to remove relay %s? (y/N) ', $relayUrl),
                    false
                );

                if (!$helper->ask($input, $output, $question)) {
                    $this->logger->info('Relay removal cancelled by user', ['url' => $relayUrl]);
                    $output->writeln('Operation cancelled');
                    return Command::SUCCESS;
                }
            }

            // Remove relay
            $success = RelayUtility::removeRelay($relayUrl);
            
            if (!$success) {
                throw new CommandException(
                    'Failed to remove relay',
                    CommandException::OPERATION_FAILED,
                    ['relay' => $relayUrl]
                );
            }

            if ($jsonOutput) {
                $output->writeln(json_encode([
                    'success' => true,
                    'relay' => $relayUrl,
                    'message' => 'Relay removed successfully'
                ], JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Relay %s removed successfully</info>', $relayUrl));
            }

            return Command::SUCCESS;
        });
    }

    private function validateRelayUrl(string $url): bool
    {
        return preg_match('/^wss?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(?::[0-9]+)?(?:\/[^\s]*)?$/', $url) === 1;
    }
} 
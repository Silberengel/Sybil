<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for listing Nostr relays
 * 
 * This command handles the 'relay-list' command, which displays
 * a list of known Nostr relays. The --raw option outputs the list
 * in JSON format.
 * 
 * Usage: nostr:relay-list [--raw]
 * 
 * Examples:
 *   sybil relay-list
 *   sybil relay-list --raw
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class RelayListCommand extends Command implements CommandInterface
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
        return 'nostr:relay-list';
    }

    public function getDescription(): string
    {
        return 'Display a list of known Nostr relays';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays a list of known Nostr relays.

<info>php %command.full_name% [--raw]</info>

Options:
  --raw       Output in JSON format

Examples:
  <info>php %command.full_name%</info>
  <info>php %command.full_name% --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('relay-list')
            ->setDescription('Display a list of known Nostr relays')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $jsonOutput = $input->getOption('json');

            // Get list of known relays
            $relays = RelayUtility::getKnownRelays();
            
            if (empty($relays)) {
                $output->writeln('<warning>No known relays found</warning>');
                return Command::SUCCESS;
            }

            if ($jsonOutput) {
                $output->writeln(json_encode($relays, JSON_PRETTY_PRINT));
            } else {
                $this->formatRelayList($relays, $output);
            }

            return Command::SUCCESS;
        });
    }

    private function formatRelayList(array $relays, OutputInterface $output): void
    {
        $output->writeln('<info>Known Nostr Relays</info>');
        
        // Print header
        $output->writeln(sprintf(
            '%-50s %-20s %-30s %-15s %-10s %-5s',
            'URL',
            'Name',
            'Description',
            'Software',
            'Version',
            'Known'
        ));
        
        // Print separator
        $output->writeln(str_repeat('-', 130));
        
        // Print rows
        foreach ($relays as $relay) {
            $output->writeln(sprintf(
                '%-50s %-20s %-30s %-15s %-10s %-5s',
                substr($relay['url'], 0, 47) . '...',
                substr($relay['name'] ?? 'Unknown', 0, 17) . '...',
                substr($relay['description'] ?? 'No description', 0, 27) . '...',
                substr($relay['software'] ?? 'Unknown', 0, 12) . '...',
                substr($relay['version'] ?? 'Unknown', 0, 7) . '...',
                $relay['is_known'] ? 'Yes' : 'No'
            ));
        }
        
        $output->writeln(str_repeat('-', 130));
        $output->writeln(sprintf('<info>Total relays: %d</info>', count($relays)));
    }
} 
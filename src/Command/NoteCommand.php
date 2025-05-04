<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for publishing a text note
 * 
 * This command handles the 'note' command, which creates and publishes
 * a text note event.
 * 
 * Usage: sybil note <content> [--relay <relay_url>] [--raw]
 * 
 * Examples:
 *   sybil note "Hello Nostr!"
 *   sybil note "Hello specific relay" --relay wss://relay.example.com
 *   sybil note "Hello with raw output" --raw
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class NoteCommand extends Command implements CommandInterface
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
        return 'nostr:note';
    }

    public function getDescription(): string
    {
        return 'Post a text note';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a text note event.

<info>php %command.full_name% <content> [--relay RELAY_URL] [--raw]</info>

Arguments:
  <content>      The note content to publish

Options:
  --relay        The relay URL to publish to (optional)
  --raw          Output raw event data

Examples:
  <info>php %command.full_name% "Hello Nostr!"</info>
  <info>php %command.full_name% "Hello specific relay" --relay wss://relay.example.com</info>
  <info>php %command.full_name% "Hello with raw output" --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('note')
            ->setDescription('Post a text note')
            ->addArgument('content', InputArgument::REQUIRED, 'The note content')
            ->addOption('relay', null, InputOption::VALUE_REQUIRED, 'The relay URL to publish to')
            ->addOption('raw', 'r', InputOption::VALUE_NONE, 'Output raw event data');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $content = $input->getArgument('content');
            if (empty($content)) {
                throw new \InvalidArgumentException('Note content cannot be empty');
            }

            // Create and publish the note
            $event = $this->eventService->createNote($content, $this->privateKey);

            $relayUrl = $input->getOption('relay');
            if ($relayUrl) {
                $this->validateRelayUrlOrFail($relayUrl);
                $this->eventService->publishToRelay($event, $relayUrl);
            } else {
                $this->eventService->publish($event);
            }

            if ($input->getOption('raw')) {
                $output->writeln(json_encode($event, JSON_PRETTY_PRINT));
            } else {
                $output->writeln('<info>Note published with ID: ' . $event->getId() . '</info>');
            }

            return Command::SUCCESS;
        });
    }
}

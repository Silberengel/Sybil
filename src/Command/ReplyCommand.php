<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for replying to a Nostr event
 * 
 * This command handles the 'reply' command, which creates and publishes
 * a reply event. For kind 1 (note) events, it creates a kind 1 reply.
 * For other event types (longform, publication, section, wiki), it creates
 * a kind 1111 (comment) reply.
 * 
 * Usage: nostr:reply <event_id> <content> [--relay <relay_url>] [--raw]
 * 
 * Examples:
 *   sybil reply <event_id> "This is a reply"
 *   sybil reply <event_id> "Reply to specific relay" --relay wss://relay.example.com
 *   sybil reply <event_id> "Reply with JSON output" --raw
 * 
 * Test examples can be found in:
 *   tests/Integration/CoreIntegrationTest.php
 */
class ReplyCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;  
    use CommandImportsTrait;
    private NostrEventService $eventService;
    private LoggerInterface $logger;
    private ParameterBagInterface $params;

    public function __construct(
        NostrEventService $eventService,
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        parent::__construct();
        $this->eventService = $eventService;
        $this->logger = $logger;
        $this->params = $params;
    }

    public function getName(): string
    {
        return 'nostr:reply';
    }

    public function getDescription(): string
    {
        return 'Reply to a Nostr event';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a reply to a Nostr event.

<info>php %command.full_name% <event_id> <content> [--relay <relay_url>] [--raw]</info>

Arguments:
  event_id    The ID of the event to reply to (64-character hex string)
  content     The content of the reply

Options:
  --relay     The relay URL to publish to (optional)
  --raw      Output in JSON format

Examples:
  <info>php %command.full_name% 1234... "This is a reply"</info>
  <info>php %command.full_name% 1234... "Reply to specific relay" --relay wss://relay.example.com</info>
  <info>php %command.full_name% 1234... "Reply with JSON output" --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('reply')
            ->setDescription('Reply to a Nostr event')
            ->addArgument('event_id', InputArgument::REQUIRED, 'The ID of the event to reply to')
            ->addArgument('content', InputArgument::REQUIRED, 'The content of the reply')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'The relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $eventId = $input->getArgument('event_id');
            $content = $input->getArgument('content');
            $relay = $input->getOption('relay');
            $jsonOutput = $input->getOption('json');

            // Validate event ID
            if (!$this->isValidEventId($eventId)) {
                throw new \RuntimeException('Invalid event ID. Must be a 64-character hex string.');
            }

            // Fetch parent event
            $parentEvent = $this->eventService->getEvent($eventId);
            if ($parentEvent === null) {
                throw new \RuntimeException('Parent event not found');
            }

            // Determine reply kind based on parent event
            $replyKind = $parentEvent->getKind() === 1 ? 1 : 1111;

            // Get relay hints from parent event
            $relayHints = $this->getRelayHints($parentEvent, $relay);

            // Get public key
            $privateKey = $this->params->get('app.private_key');
            $publicKey = KeyUtility::derivePublicKey($privateKey);

            // Create event data
            $eventData = [
                'kind' => $replyKind,
                'content' => $content,
                'tags' => [
                    ['e', $eventId, '', 'reply'],
                    ['p', $parentEvent->getPubkey()],
                    ...$relayHints,
                ],
                'created_at' => time(),
                'pubkey' => $publicKey,
            ];

            // Create and publish event
            $event = $this->eventService->createEvent($eventData);
            $success = $this->eventService->publishEvent($event);

            if (!$success) {
                throw new \RuntimeException('Failed to publish reply');
            }

            if ($jsonOutput) {
                $output->writeln(json_encode($event->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Reply published successfully with ID: %s</info>', $event->getId()));
                $output->writeln(sprintf('<info>Reply type: %s</info>', $replyKind === 1 ? 'Note' : 'Comment'));
                if (!empty($relayHints)) {
                    $output->writeln('<info>Relay hints included:</info>');
                    foreach ($relayHints as $hint) {
                        $output->writeln(sprintf('  - %s', $hint[1]));
                    }
                }
            }

            return Command::SUCCESS;
        });
    }

    private function isValidEventId(string $eventId): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $eventId) === 1;
    }

    private function getRelayHints($parentEvent, ?string $specifiedRelay): array
    {
        $relayHints = [];

        // Add specified relay if provided
        if ($specifiedRelay !== null) {
            $relayHints[] = ['r', $specifiedRelay];
        }

        // Add relay hints from parent event
        foreach ($parentEvent->getTags() as $tag) {
            if ($tag[0] === 'r') {
                $relayHints[] = $tag;
            }
        }

        return $relayHints;
    }
} 
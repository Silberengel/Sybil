<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for deleting a Nostr event
 * 
 * This command handles the 'delete' command, which creates and publishes
 * a deletion event for a specific event.
 * Usage: nostr:delete <event_id> [--relay <relay_url>] [--reason <reason>]
 */
class DeleteCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;
    use CommandImportsTrait;

    private NostrEventService $eventService;
    private LoggerInterface $logger;
    private string $privateKey;
    private const MAX_REASON_LENGTH = 1000;

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
        return 'nostr:delete';
    }

    public function getDescription(): string
    {
        return 'Delete a Nostr event by creating a deletion event';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a deletion event for a specific event.

<info>php %command.full_name% <event_id> [--reason <reason>] [--raw]</info>

Arguments:
  <event_id>    The ID of the event to delete (64-character hex string)

Options:
  --reason      Optional reason for deletion (max 1000 characters)
  --raw         Output raw event data in JSON format

Examples:
  <info>php %command.full_name% 1234...abcd</info>
  <info>php %command.full_name% 1234...abcd --reason "Content is no longer relevant"</info>
  <info>php %command.full_name% 1234...abcd --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('delete')
            ->setDescription('Delete a Nostr event by creating a deletion event')
            ->addArgument('event_id', InputArgument::REQUIRED, 'The ID of the event to delete')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason for deletion (max 1000 characters)')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output raw event data');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $eventId = $input->getArgument('event_id');
            $reason = $input->getOption('reason');
            $raw = $input->getOption('raw');

            if (!$eventId) {
                throw new \RuntimeException('Event ID is required');
            }

            // Validate event ID
            if (!$this->isValidEventId($eventId)) {
                throw new \RuntimeException('Invalid event ID. Must be a 64-character hex string.');
            }

            // Validate reason length
            if ($reason && strlen($reason) > self::MAX_REASON_LENGTH) {
                throw new \RuntimeException(sprintf('Reason must not exceed %d characters', self::MAX_REASON_LENGTH));
            }

            // Get public key for authentication
            $publicKey = $this->getPublicKey();

            // Create event data
            $eventData = [
                'kind' => 5, // Deletion
                'content' => $reason ?? '',
                'tags' => [
                    ['e', $eventId],
                ],
                'created_at' => time(),
                'pubkey' => $publicKey,
            ];

            // Create and publish event
            $event = $this->eventService->createEvent($eventData);
            $success = $this->eventService->publishEvent($event, $publicKey);

            if (!$success) {
                throw new \RuntimeException('Failed to publish deletion event');
            }

            if ($raw) {
                $output->writeln(json_encode($event->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Deletion event published successfully with ID: %s</info>', $event->getId()));
                if ($reason) {
                    $output->writeln(sprintf('<info>Reason: %s</info>', $reason));
                }
            }

            return Command::SUCCESS;
        });
    }

    private function isValidEventId(string $eventId): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $eventId) === 1;
    }

    private function getPublicKey(): string
    {
        try {
            $keyPair = new KeyPair($this->privateKey);
            return $keyPair->getPublicKey();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to derive public key: ' . $e->getMessage());
        }
    }
} 
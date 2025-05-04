<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for publishing a publication
 * 
 * This command handles the 'publication' command, which creates and publishes
 * a publication event. Publications are created from AsciiDoc files with YAML
 * front matter containing metadata like title, author, tags, etc.
 * 
 * The command creates:
 * - A kind 30040 event for the main publication
 * - Multiple kind 30041 events for each section
 * 
 * Usage: nostr:publication <file_path> [--relay <relay_url>] [--json]
 * 
 * Examples:
 *   sybil publication tests/Publications/Books/AesopsFables.adoc
 *   sybil publication tests/Publications/Books/AesopsFables.adoc --relay wss://relay.example.com
 *   sybil publication tests/Publications/Books/AesopsFables.adoc --json
 * 
 * Test examples can be found in:
 *   tests/Integration/ArticleIntegrationTest.php
 */
class PublicationCommand extends Command implements CommandInterface
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
        return 'nostr:publication';
    }

    public function getDescription(): string
    {
        return 'Create and publish a publication';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a publication event.

<info>php %command.full_name% <file_path> [--relay RELAY_URL] [--json]</info>

Arguments:
  <file_path>    Path to the AsciiDoc file containing the publication

Options:
  --relay        The relay URL to publish to (optional)
  --json          Output raw event data

Examples:
  <info>php %command.full_name% tests/Publications/Books/AesopsFables.adoc</info>
  <info>php %command.full_name% tests/Publications/Books/AesopsFables.adoc --relay wss://relay.example.com</info>
  <info>php %command.full_name% tests/Publications/Books/AesopsFables.adoc --json</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('publication')
            ->setDescription('Create and publish a publication')
            ->addArgument('file_path', InputArgument::REQUIRED, 'Path to the AsciiDoc file')
            ->addOption('relay', null, InputOption::VALUE_REQUIRED, 'The relay URL to publish to')
            ->addOption('raw', 'r', InputOption::VALUE_NONE, 'Output raw event data');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $filePath = $input->getArgument('file_path');
            $raw = $input->getOption('raw');

            if (!file_exists($filePath)) {
                throw new \RuntimeException(sprintf('File not found: %s', $filePath));
            }

            // Get content and metadata
            $result = $this->getPublicationContent($filePath);
            if ($result === null) {
                throw new \RuntimeException('Invalid content or metadata');
            }

            $content = $result['content'];
            $metadata = $result['metadata'];

            // Get public key for logging context
            $publicKey = KeyUtility::getPublicKeyFromPrivateKey($this->privateKey);

            // Create event data
            $eventData = [
                'kind' => 30023, // Long-form content
                'content' => $content,
                'tags' => [
                    ['d', $metadata['title']],
                    ['title', $metadata['title']],
                    ['summary', $metadata['summary']],
                    ['image', $metadata['image']],
                    ...array_map(fn($tag) => ['t', $tag], $metadata['tags'])
                ],
                'created_at' => time(),
                'pubkey' => $publicKey,
            ];

            // Create and publish event
            $event = $this->eventService->createEvent($eventData);

            $relayUrl = $input->getOption('relay');
            if ($relayUrl) {
                $success = $this->eventService->publishToRelay($event, $relayUrl);
            } else {
                $success = $this->eventService->publish($event);
            }

            if (!$success) {
                throw new \RuntimeException('Failed to publish publication');
            }

            if ($raw) {
                $output->writeln(json_encode($event->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Publication published successfully with ID: %s</info>', $event->getId()));
                $output->writeln(sprintf('<info>Title: %s</info>', $metadata['title']));
                $output->writeln(sprintf('<info>Summary: %s</info>', $metadata['summary']));
            }

            return Command::SUCCESS;
        });
    }

    private function getPublicationContent(string $filePath): ?array
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException('Failed to read file');
            }

            // Parse front matter
            $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)/s';
            if (!preg_match($pattern, $content, $matches)) {
                throw new \RuntimeException('Invalid front matter format');
            }

            $frontMatter = yaml_parse($matches[1]);
            $content = $matches[2];

            if ($frontMatter === false || !isset($frontMatter['title'])) {
                throw new \RuntimeException('Invalid front matter content');
            }

            return [
                'content' => $content,
                'metadata' => [
                    'title' => $frontMatter['title'],
                    'summary' => $frontMatter['summary'] ?? '',
                    'tags' => $frontMatter['tags'] ?? [],
                    'image' => $frontMatter['image'] ?? '',
                ],
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Error parsing publication content: ' . $e->getMessage());
        }
    }
} 
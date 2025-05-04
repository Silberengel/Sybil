<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for publishing a longform article
 * 
 * This command handles the 'longform' command, which creates and publishes
 * a longform article event from a Markdown file with YAML front matter
 * containing metadata like title, author, tags, etc.
 * 
 * The command creates a kind 30023 event for the longform article.
 * 
 * Usage: sybil longform <file_path> [--relay <relay_url>] [--json]
 * 
 * Examples:
 *   sybil longform tests/Fixtures/Markdown_testfile.md
 *   sybil longform tests/Fixtures/Markdown_testfile.md --relay wss://relay.example.com
 *   sybil longform tests/Fixtures/Markdown_testfile.md --json
 * 
 * Test examples can be found in:
 *   tests/Integration/ArticleIntegrationTest.php
 */
class LongformCommand extends Command implements CommandInterface
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
        return 'nostr:longform';
    }

    public function getDescription(): string
    {
        return 'Create and publish a longform article event from a Markdown file';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a longform article event from a Markdown file.

<info>php %command.full_name% <file_path> [--relay <relay_url>] [--json]</info>

Arguments:
  <file_path>    Path to the Markdown file or "-" to read from stdin

Options:
  --relay (-r)   Relay URL to publish to
  --json         Output raw event data in JSON format

Examples:
  <info>php %command.full_name% article.md</info>
  <info>php %command.full_name% article.md --relay wss://relay.example.com</info>
  <info>php %command.full_name% article.md --json</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('longform')
            ->setDescription('Create and publish a longform article event from a Markdown file')
            ->addArgument('file_path', InputArgument::REQUIRED, 'Path to the Markdown file or "-" to read from stdin')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to publish to')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output raw event data in JSON format');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $filePath = $input->getArgument('file_path');
            $relay = $input->getOption('relay');
            $raw = $input->getOption('raw');

            // Get content and metadata
            $result = $this->getLongformContent($filePath);
            if ($result === null) {
                throw new \RuntimeException('Invalid content or metadata');
            }

            $content = $result['content'];
            $metadata = $result['metadata'];

            // Get public key for authentication
            $publicKey = $this->getPublicKey();

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
            $success = $this->eventService->publishEvent($event, $relay, $publicKey);

            if (!$success) {
                throw new \RuntimeException('Failed to publish longform article');
            }

            if ($raw) {
                $output->writeln(json_encode($event->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Longform article published successfully with ID: %s</info>', $event->getId()));
                $output->writeln(sprintf('<info>Title: %s</info>', $metadata['title']));
                $output->writeln(sprintf('<info>Summary: %s</info>', $metadata['summary']));
            }

            return Command::SUCCESS;
        });
    }

    private function getLongformContent(string $input): ?array
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

            // Parse front matter
            $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)/s';
            if (!preg_match($pattern, $input, $matches)) {
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
            throw new \RuntimeException('Error parsing longform content: ' . $e->getMessage());
        }
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

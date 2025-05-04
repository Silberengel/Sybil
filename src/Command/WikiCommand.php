<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for publishing a wiki article
 * 
 * This command handles the 'wiki' command, which creates and publishes
 * a wiki article event. Wiki articles are created from AsciiDoc files with YAML
 * front matter containing metadata like title, author, tags, etc.
 * 
 * The command creates a kind 30024 event for the wiki article.
 * 
 * Usage: nostr:wiki <file_path> [--relay <relay_url>] [--json]
 * 
 * Examples:
 *   sybil wiki tests/Fixtures/Wiki_testfile.adoc
 *   sybil wiki tests/Fixtures/Wiki_testfile.adoc --relay wss://relay.example.com
 *   sybil wiki tests/Fixtures/Wiki_testfile.adoc --json
 * 
 * Test examples can be found in:
 *   tests/Integration/ArticleIntegrationTest.php
 */
class WikiCommand extends Command implements CommandInterface
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
        return 'nostr:wiki';
    }

    public function getDescription(): string
    {
        return 'Create and publish a wiki article';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a wiki article.

<info>php %command.full_name% <file_path> [--relay <relay_url>] [--json]</info>

Arguments:
  file_path   The path to the AsciiDoc file containing the wiki article

Options:
  --relay     The relay URL to publish to (optional)
  --json      Output in JSON format

Examples:
  <info>php %command.full_name% tests/Fixtures/Wiki_testfile.adoc</info>
  <info>php %command.full_name% tests/Fixtures/Wiki_testfile.adoc --relay wss://relay.example.com</info>
  <info>php %command.full_name% tests/Fixtures/Wiki_testfile.adoc --json</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('wiki')
            ->setDescription('Create and publish a wiki article')
            ->addArgument('file_path', InputArgument::REQUIRED, 'The path to the AsciiDoc file containing the wiki article')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'The relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $filePath = $input->getArgument('file_path');
            $relay = $input->getOption('relay');
            $jsonOutput = $input->getOption('json');

            // Get content and metadata
            $result = $this->getWikiContent($filePath);
            if ($result === null) {
                throw new \RuntimeException('Invalid content or metadata');
            }

            $content = $result['content'];
            $metadata = $result['metadata'];

            // Get public key
            $privateKey = $this->params->get('app.private_key');
            $publicKey = KeyUtility::derivePublicKey($privateKey);

            // Create event data
            $eventData = [
                'kind' => 30024, // Wiki article
                'content' => $content,
                'tags' => [
                    ['d', $metadata['title']],
                    ['title', $metadata['title']],
                    ...array_map(fn($tag) => ['t', $tag], $metadata['tags'])
                ],
                'created_at' => time(),
                'pubkey' => $publicKey,
            ];

            // Create and publish event
            $event = $this->eventService->createEvent($eventData);
            $success = $this->eventService->publishEvent($event);

            if (!$success) {
                throw new \RuntimeException('Failed to publish wiki article');
            }

            if ($jsonOutput) {
                $output->writeln(json_encode($event->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Wiki article published successfully with ID: %s</info>', $event->getId()));
                $output->writeln(sprintf('<info>Title: %s</info>', $metadata['title']));
            }

            return Command::SUCCESS;
        });
    }

    private function getWikiContent(string $input): ?array
    {
        try {
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
                    'tags' => $frontMatter['tags'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Error parsing wiki content: ' . $e->getMessage());
        }
    }
} 
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
 * Usage: nostr:wiki <file_path> [--relay <relay_url>] [--raw]
 * 
 * Examples:
 *   sybil wiki tests/Fixtures/Wiki_testfile.adoc
 *   sybil wiki tests/Fixtures/Wiki_testfile.adoc --relay wss://relay.example.com
 *   sybil wiki tests/Fixtures/Wiki_testfile.adoc --raw
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
        return 'Create and publish a wiki article (kind 30050)';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a wiki article.

<info>php %command.full_name% <file_path> [--relay <relay_url>] [--raw]</info>

Arguments:
  file_path   The path to the AsciiDoc file containing the wiki article

Options:
  --relay     The relay URL to publish to (optional)
  --raw      Output in JSON format

Examples:
  <info>php %command.full_name% tests/Fixtures/Wiki_testfile.adoc</info>
  <info>php %command.full_name% tests/Fixtures/Wiki_testfile.adoc --relay wss://relay.example.com</info>
  <info>php %command.full_name% tests/Fixtures/Wiki_testfile.adoc --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('wiki')
            ->setDescription('Create a wiki article (kind 30818)')
            ->setHelp(<<<'HELP'
Create a wiki article with the specified content and metadata.

The article will be published as a kind 30818 event with the following MIME types:
- m: text/asciidoc
- M: article/wiki/replaceable

Required options:
  --title: The title of the wiki article
  --content: The content of the wiki article in AsciiDoc format

Optional options:
  --author: The author of the wiki article
  --image: URL to an image for the wiki article
  --tags: Comma-separated list of hashtags
  --citations: Comma-separated list of citation event IDs to reference

Example:
  sybil wiki --title "My Wiki Article" --content "Article content in AsciiDoc format" --author "John Doe" --tags "wiki,example" --citations "event1,event2"
HELP
            )
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title of the wiki article')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Content of the wiki article in AsciiDoc format')
            ->addOption('author', null, InputOption::VALUE_OPTIONAL, 'Author of the wiki article')
            ->addOption('image', null, InputOption::VALUE_OPTIONAL, 'URL to an image for the wiki article')
            ->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of hashtags')
            ->addOption('citations', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of citation event IDs to reference');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $filePath = $input->getArgument('file_path');
            $json = $input->getOption('json');
            $citations = $input->getOption('citations');

            if (!file_exists($filePath)) {
                throw new \RuntimeException(sprintf('File not found: %s', $filePath));
            }

            // Get content and metadata
            $result = $this->getArticleContent($filePath);
            if ($result === null) {
                throw new \RuntimeException('Invalid content or metadata');
            }

            $content = $result['content'];
            $metadata = $result['metadata'];

            // Get public key for logging context
            $publicKey = KeyUtility::getPublicKeyFromPrivateKey($this->privateKey);

            // Create article event
            $articleEvent = $this->createArticleEvent($metadata, $content, $publicKey, $citations);
            $this->eventService->publish($articleEvent);

            if ($json) {
                $output->writeln(json_encode($articleEvent->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Wiki article published successfully!</info>'));
                $output->writeln(sprintf('<info>Article ID: %s</info>', $articleEvent->getId()));
                $output->writeln(sprintf('<info>Title: %s</info>', $metadata['title']));
                if ($citations) {
                    $output->writeln(sprintf('<info>Citations: %s</info>', $citations));
                }
            }

            return Command::SUCCESS;
        });
    }

    private function createArticleEvent(array $metadata, string $content, string $publicKey, ?string $citations): NostrEvent
    {
        $eventData = [
            'kind' => 30050,
            'content' => $content,
            'tags' => [
                ['d', $metadata['title']],
                ['title', $metadata['title']],
                ['summary', $metadata['summary'] ?? ''],
                ['image', $metadata['image'] ?? ''],
                ...array_map(fn($tag) => ['t', $tag], $metadata['tags'] ?? [])
            ],
            'created_at' => time(),
            'pubkey' => $publicKey,
        ];

        // Add citation references if provided
        if ($citations) {
            foreach (explode(',', $citations) as $citationId) {
                $eventData['tags'][] = ['e', trim($citationId), '', 'citation'];
            }
        }

        return $this->eventService->createEvent($eventData);
    }

    private function getArticleContent(string $input): ?array
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
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
 * Usage: sybil longform <file_path> [--relay <relay_url>] [--raw]
 * 
 * Examples:
 *   sybil longform tests/Fixtures/Markdown_testfile.md
 *   sybil longform tests/Fixtures/Markdown_testfile.md --relay wss://relay.example.com
 *   sybil longform tests/Fixtures/Markdown_testfile.md --raw
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

<info>php %command.full_name% <file_path> [--relay <relay_url>] [--raw]</info>

Arguments:
  <file_path>    Path to the Markdown file or "-" to read from stdin

Options:
  --relay (-r)   Relay URL to publish to
  --raw         Output raw event data in JSON format

Examples:
  <info>php %command.full_name% article.md</info>
  <info>php %command.full_name% article.md --relay wss://relay.example.com</info>
  <info>php %command.full_name% article.md --raw</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('longform')
            ->setDescription('Create a longform article (kind 30023)')
            ->setHelp(<<<'HELP'
Create a longform article with the specified content and metadata.

The article will be published as a kind 30023 event with the following MIME types:
- m: text/markdown
- M: article/longform/replaceable

Required options:
  --title: The title of the article
  --content: The content of the article in Markdown format
  --d-tag: A unique identifier for the article

Optional options:
  --author: The author of the article
  --image: URL to an image for the article
  --tags: Comma-separated list of hashtags
  --summary: A brief summary of the article
  --published-at: Publication date (ISO 8601 format)
  --canonical-url: URL to the canonical version of the article

Example:
  sybil longform --title "My Article" --content "Article content in Markdown format" --d-tag "my-article" --author "John Doe" --tags "article,example" --summary "A brief summary" --published-at "2024-03-20T12:00:00Z" --canonical-url "https://example.com/article"
HELP
            )
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title of the article')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Content of the article in Markdown format')
            ->addOption('d-tag', null, InputOption::VALUE_REQUIRED, 'Unique identifier for the article')
            ->addOption('author', null, InputOption::VALUE_OPTIONAL, 'Author of the article')
            ->addOption('image', null, InputOption::VALUE_OPTIONAL, 'URL to an image for the article')
            ->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of hashtags')
            ->addOption('summary', null, InputOption::VALUE_OPTIONAL, 'Brief summary of the article')
            ->addOption('published-at', null, InputOption::VALUE_OPTIONAL, 'Publication date (ISO 8601 format)')
            ->addOption('canonical-url', null, InputOption::VALUE_OPTIONAL, 'URL to the canonical version of the article');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
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
                $output->writeln(sprintf('<info>Article published successfully!</info>'));
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
            'kind' => 30023,
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

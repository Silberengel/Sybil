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
 * Usage: nostr:publication <file_path> [--relay <relay_url>] [--raw]
 * 
 * Examples:
 *   sybil publication tests/Publications/Books/AesopsFables.adoc
 *   sybil publication tests/Publications/Books/AesopsFables.adoc --relay wss://relay.example.com
 *   sybil publication tests/Publications/Books/AesopsFables.adoc --raw
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
        return 'Create and publish a publication (kinds 30040, 30041)';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a publication using kinds 30040 and 30041.

This command follows NKBIP-01 for publications. It creates events that:
- Announce a publication (kind 30040) with metadata
- Create content events (kind 30041) for each section
- Support replies (kind 1111) and highlights (kind 9802)
- Can be referenced by other publications

Example:
  <info>%command.full_name% tests/Publications/Books/AesopsFables.adoc</info>

Required:
- File Path: Path to the AsciiDoc file containing the publication
  The file must have YAML front matter with:
  - title: Publication title
  - summary: Brief description
  - image: Cover image URL
  - tags: List of tags
  - sections: List of section titles (optional)

Optional:
- Relay (--relay): Relay URL to publish to
- JSON (--raw): Output raw event data
- Citations (--citations): Comma-separated list of citation event IDs to reference

The command automatically:
- Creates a kind 30040 event for the publication with:
  - Title, summary, image, and tags
  - Creation timestamp
  - Author information
- Creates kind 30041 events for each section with:
  - Section content
  - Reference to parent publication
  - Section title and number
- Handles replies and highlights
- Validates content and metadata
- Adds proper tags for references

Example AsciiDoc file:
---
title: My Publication
summary: A brief description
image: https://example.com/cover.jpg
tags: [nostr, publication, example]
sections:
  - Introduction
  - Chapter 1
  - Chapter 2
---

= Introduction

This is the introduction...

= Chapter 1

First chapter content...

= Chapter 2

Second chapter content...
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('publication')
            ->setDescription('Create a publication (kinds 30040 and 30041)')
            ->setHelp(<<<'HELP'
Create a publication with the specified content and metadata.

The publication will be published as two events:
1. Publication Index (kind 30040):
   - m: application/json
   - M: meta-data/index/replaceable

2. Publication Content (kind 30041):
   - m: text/asciidoc
   - M: article/publication-content/replaceable

Required options:
  --title: The title of the publication
  --content: The content of the publication in AsciiDoc format
  --d-tag: A unique identifier for the publication

Optional options:
  --author: The author of the publication
  --image: URL to an image for the publication
  --tags: Comma-separated list of hashtags
  --summary: A brief summary of the publication
  --published-at: Publication date (ISO 8601 format)

Example:
  sybil publication --title "My Publication" --content "Publication content in AsciiDoc format" --d-tag "my-publication" --author "John Doe" --tags "publication,example" --summary "A brief summary" --published-at "2024-03-20T12:00:00Z"
HELP
            )
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title of the publication')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Content of the publication in AsciiDoc format')
            ->addOption('d-tag', null, InputOption::VALUE_REQUIRED, 'Unique identifier for the publication')
            ->addOption('author', null, InputOption::VALUE_OPTIONAL, 'Author of the publication')
            ->addOption('image', null, InputOption::VALUE_OPTIONAL, 'URL to an image for the publication')
            ->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of hashtags')
            ->addOption('summary', null, InputOption::VALUE_OPTIONAL, 'Brief summary of the publication')
            ->addOption('published-at', null, InputOption::VALUE_OPTIONAL, 'Publication date (ISO 8601 format)');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $filePath = $input->getArgument('file_path');
            $json = $input->getOption('json');

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
            $sections = $result['sections'];

            // Get public key for logging context
            $publicKey = KeyUtility::getPublicKeyFromPrivateKey($this->privateKey);

            // Create publication event
            $publicationEvent = $this->createPublicationEvent($metadata, $content, $publicKey);
            $this->eventService->publish($publicationEvent);

            // Create section events
            $sectionEvents = [];
            foreach ($sections as $section) {
                $sectionEvent = $this->createSectionEvent($section, $publicationEvent->getId(), $publicKey);
                $this->eventService->publish($sectionEvent);
                $sectionEvents[] = $sectionEvent;
            }

            if ($json) {
                $output->writeln(json_encode([
                    'publication' => $publicationEvent->toArray(),
                    'sections' => array_map(fn($e) => $e->toArray(), $sectionEvents)
                ], JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Publication published successfully!</info>'));
                $output->writeln(sprintf('<info>Publication ID: %s</info>', $publicationEvent->getId()));
                $output->writeln(sprintf('<info>Title: %s</info>', $metadata['title']));
                $output->writeln(sprintf('<info>Sections: %d</info>', count($sections)));
            }

            return Command::SUCCESS;
        });
    }

    private function createPublicationEvent(array $metadata, string $content, string $publicKey): NostrEvent
    {
        $eventData = [
            'kind' => 30040,
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

        return $this->eventService->createEvent($eventData);
    }

    private function createSectionEvent(array $section, string $publicationId, string $publicKey): NostrEvent
    {
        $eventData = [
            'kind' => 30041,
            'content' => $section['content'],
            'tags' => [
                ['d', $section['title']],
                ['title', $section['title']],
                ['number', (string) $section['number']],
                ['e', $publicationId, '', 'root'],
            ],
            'created_at' => time(),
            'pubkey' => $publicKey,
        ];

        return $this->eventService->createEvent($eventData);
    }

    private function parseSections(string $content): array
    {
        $sections = [];
        $pattern = '/^= ([^\n]+)\n\n(.*?)(?=\n\n= |$)/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $sections[] = [
                'title' => $match[1],
                'content' => trim($match[2]),
            ];
        }

        return $sections;
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
                    'sections' => $frontMatter['sections'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Error parsing publication content: ' . $e->getMessage());
        }
    }
} 
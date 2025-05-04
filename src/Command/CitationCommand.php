<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for creating and publishing citations
 * 
 * This command handles the 'citation' command, which creates and publishes
 * citation events (kinds 30-33) according to NKBIP-03.
 * 
 * Usage: sybil citation [--event-id <article_id>] [--quote <quoted_text>] [--comment <comment_text>] [--context <surrounding_text>] [--relay <relay_url>] [--raw]
 */
class CitationCommand extends Command implements CommandInterface
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
        return 'citation';
    }

    public function getDescription(): string
    {
        return 'Create and publish a citation (kinds 30-33)';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command creates and publishes a citation using kinds 30-33.

This command follows NKBIP-03 for citations. It creates events that:
- Support four types of citations:
  - Internal reference (kind 30): References other Nostr events
  - External web reference (kind 31): References web content
  - Hardcopy reference (kind 32): References printed material
  - Prompt reference (kind 33): References LLM prompts

Required for all types:
- Type (--type): Citation type (internal, web, hardcopy, prompt)
- Title (--title): Title to display for citation
- Author (--author): Author to display for citation
- Content (--content): Text cited
- Accessed On (--accessed-on): Date-time accessed in ISO 8601 format

Type-specific requirements:
Internal reference (kind 30):
- Nostr Reference (--nostr-ref): Kind:pubkey:event_id format
- Relay Hint (--relay-hint): Relay hint for the reference

External web reference (kind 31):
- URL (--url): URL where citation was accessed

Optional for all types:
- Summary (--summary): Short explanation of topics covered
- Published On (--published-on): Date-time published in ISO 8601 format
- Location (--location): Where it was written or published
- Geohash (--geohash): Geohash of the precise location

Additional optional fields by type:
Web reference (kind 31):
- Published By (--published-by): Who published the citation
- Version (--version): Version or edition
- Open Timestamp (--open-timestamp): Open timestamp event ID

Hardcopy reference (kind 32):
- Page Range (--page-range): Pages the citation is found on
- Chapter Title (--chapter-title): Chapter or section title
- Editor (--editor): Who edited the publication
- Published In (--published-in): Journal name and volume
- DOI (--doi): DOI number
- Version (--version): Version or edition
- Published By (--published-by): Who published the citation

Prompt reference (kind 33):
- LLM (--llm): Language model used for the prompt
- Version (--version): Version or edition of the model
- URL (--url): Website LLM was accessed from

Note: Citations can be referenced in articles using different display types:
Traditional citations:
- [[citation::end::nevent...]] Endnotes in "References" section
- [[citation::foot::nevent...]] Footnotes with superscript numbers
- [[citation::foot-end::nevent...]] Footnotes linking to endnotes
- [[citation::inline::nevent...]] In-line references like "(Author, Year, p. X)"
- [[citation::quote::nevent...]] Quoted content with quote-header and endnote

AI citations:
- [[citation::prompt-end::nevent...]] AI citations in "References" section
- [[citation::prompt-inline::nevent...]] AI citations next to referenced text

Examples:
  # Internal reference (kind 30)
  <info>%command.full_name% --type internal --title "Example Post" --author "John Doe" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --nostr-ref "1:pubkey:event_id" --relay-hint "wss://relay.example.com"</info>

  # Web reference (kind 31)
  <info>%command.full_name% --type web --title "Example Article" --author "Jane Smith" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --url "https://example.com/article"</info>

  # Hardcopy reference (kind 32)
  <info>%command.full_name% --type hardcopy --title "Example Book" --author "Bob Wilson" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --page-range "123-125" --published-by "Example Press"</info>

  # Prompt reference (kind 33)
  <info>%command.full_name% --type prompt --title "Example Prompt" --author "ChatGPT" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --llm "ChatGPT" --version "4.0"</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('citation')
            ->setDescription('Create and publish a citation (kinds 30-33)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Citation type (internal, web, hardcopy, prompt)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Title to display for citation')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'Author to display for citation')
            ->addOption('content', 'c', InputOption::VALUE_REQUIRED, 'Text cited')
            ->addOption('summary', 's', InputOption::VALUE_REQUIRED, 'Short explanation of topics covered')
            ->addOption('accessed-on', null, InputOption::VALUE_REQUIRED, 'Date-time accessed in ISO 8601 format')
            ->addOption('published-on', null, InputOption::VALUE_REQUIRED, 'Date-time published in ISO 8601 format')
            ->addOption('location', null, InputOption::VALUE_REQUIRED, 'Where it was written or published')
            ->addOption('geohash', 'g', InputOption::VALUE_REQUIRED, 'Geohash of the precise location')
            // Internal reference specific options
            ->addOption('nostr-ref', null, InputOption::VALUE_REQUIRED, 'Nostr reference (kind:pubkey:event_id)')
            ->addOption('relay-hint', null, InputOption::VALUE_REQUIRED, 'Relay hint for internal reference')
            // Web reference specific options
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'URL where citation was accessed')
            ->addOption('published-by', null, InputOption::VALUE_REQUIRED, 'Who published the citation')
            ->addOption('version', 'v', InputOption::VALUE_REQUIRED, 'Version or edition')
            ->addOption('open-timestamp', null, InputOption::VALUE_REQUIRED, 'Open timestamp event ID')
            // Hardcopy specific options
            ->addOption('page-range', null, InputOption::VALUE_REQUIRED, 'Pages the citation is found on')
            ->addOption('chapter-title', null, InputOption::VALUE_REQUIRED, 'Chapter or section title')
            ->addOption('editor', null, InputOption::VALUE_REQUIRED, 'Who edited the publication')
            ->addOption('published-in', null, InputOption::VALUE_REQUIRED, 'Journal name and volume')
            ->addOption('doi', null, InputOption::VALUE_REQUIRED, 'DOI number')
            // Prompt specific options
            ->addOption('llm', null, InputOption::VALUE_REQUIRED, 'Language model used for the prompt')
            ->addOption('relay', null, InputOption::VALUE_REQUIRED, 'The relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output raw event data');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeWithErrorHandling($input, $output, function (InputInterface $input, OutputInterface $output) {
            $type = $input->getOption('type');
            $title = $input->getOption('title');
            $author = $input->getOption('author');
            $content = $input->getOption('content');
            $accessedOn = $input->getOption('accessed-on');
            $json = $input->getOption('json');

            // Validate required fields
            if (!$type || !$title || !$author || !$content || !$accessedOn) {
                throw new \RuntimeException('Type, title, author, content, and accessed-on are required');
            }

            // Get public key for logging context
            $publicKey = KeyUtility::getPublicKeyFromPrivateKey($this->privateKey);

            // Create citation event based on type
            $citationEvent = match ($type) {
                'internal' => $this->createInternalCitation($input, $publicKey),
                'web' => $this->createWebCitation($input, $publicKey),
                'hardcopy' => $this->createHardcopyCitation($input, $publicKey),
                'prompt' => $this->createPromptCitation($input, $publicKey),
                default => throw new \RuntimeException('Invalid citation type')
            };

            $this->eventService->publish($citationEvent);

            if ($json) {
                $output->writeln(json_encode($citationEvent->toArray(), JSON_PRETTY_PRINT));
            } else {
                $output->writeln(sprintf('<info>Citation published successfully!</info>'));
                $output->writeln(sprintf('<info>Citation ID: %s</info>', $citationEvent->getId()));
                $output->writeln(sprintf('<info>Type: %s</info>', $type));
                $output->writeln(sprintf('<info>Title: %s</info>', $title));
                $output->writeln(sprintf('<info>Author: %s</info>', $author));
            }

            return Command::SUCCESS;
        });
    }

    private function createInternalCitation(InputInterface $input, string $publicKey): NostrEvent
    {
        $nostrRef = $input->getOption('nostr-ref');
        $relayHint = $input->getOption('relay-hint');

        if (!$nostrRef) {
            throw new \RuntimeException('Nostr reference is required for internal citations');
        }

        $eventData = [
            'kind' => 30,
            'content' => $input->getOption('content'),
            'tags' => [
                ['c', $nostrRef, $relayHint ?? ''],
                ['published_on', $input->getOption('published-on') ?? ''],
                ['title', $input->getOption('title')],
                ['author', $input->getOption('author')],
            ],
            'created_at' => time(),
            'pubkey' => $publicKey,
        ];

        // Add optional tags
        $this->addOptionalTags($eventData, $input, ['accessed_on', 'location', 'g', 'summary']);

        return $this->eventService->createEvent($eventData);
    }

    private function createWebCitation(InputInterface $input, string $publicKey): NostrEvent
    {
        $url = $input->getOption('url');
        if (!$url) {
            throw new \RuntimeException('URL is required for web citations');
        }

        $eventData = [
            'kind' => 31,
            'content' => $input->getOption('content'),
            'tags' => [
                ['u', $url],
                ['accessed_on', $input->getOption('accessed-on')],
                ['title', $input->getOption('title')],
                ['author', $input->getOption('author')],
            ],
            'created_at' => time(),
            'pubkey' => $publicKey,
        ];

        // Add optional tags
        $this->addOptionalTags($eventData, $input, [
            'published_on', 'published_by', 'version', 'location', 'g',
            'open_timestamp', 'summary'
        ]);

        return $this->eventService->createEvent($eventData);
    }

    private function createHardcopyCitation(InputInterface $input, string $publicKey): NostrEvent
    {
        $eventData = [
            'kind' => 32,
            'content' => $input->getOption('content'),
            'tags' => [
                ['accessed_on', $input->getOption('accessed-on')],
                ['title', $input->getOption('title')],
                ['author', $input->getOption('author')],
            ],
            'created_at' => time(),
            'pubkey' => $publicKey,
        ];

        // Add optional tags
        $this->addOptionalTags($eventData, $input, [
            'page_range', 'chapter_title', 'editor', 'published_on',
            'published_by', 'published_in', 'doi', 'version', 'location',
            'g', 'summary'
        ]);

        return $this->eventService->createEvent($eventData);
    }

    private function createPromptCitation(InputInterface $input, string $publicKey): NostrEvent
    {
        $llm = $input->getOption('llm');
        if (!$llm) {
            throw new \RuntimeException('LLM is required for prompt citations');
        }

        $eventData = [
            'kind' => 33,
            'content' => $input->getOption('content'),
            'tags' => [
                ['llm', $llm],
                ['accessed_on', $input->getOption('accessed-on')],
                ['version', $input->getOption('version') ?? ''],
                ['summary', $input->getOption('summary') ?? ''],
            ],
            'created_at' => time(),
            'pubkey' => $publicKey,
        ];

        // Add optional URL tag
        if ($url = $input->getOption('url')) {
            $eventData['tags'][] = ['u', $url];
        }

        return $this->eventService->createEvent($eventData);
    }

    private function addOptionalTags(array &$eventData, InputInterface $input, array $tagNames): void
    {
        foreach ($tagNames as $tagName) {
            if ($value = $input->getOption($tagName)) {
                $eventData['tags'][] = [$tagName, $value];
            }
        }
    }
} 
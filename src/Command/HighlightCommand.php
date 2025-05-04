<?php

namespace App\Command;

use App\Service\NostrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'highlight',
    description: 'Create a highlight (kind 9802) of content',
)]
class HighlightCommand extends Command
{
    public function __construct(
        private readonly NostrService $nostrService,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command creates a highlight (kind 9802) of content.

Highlights can be created for:
- Nostr events (using --event-id)
- URLs (using --url)
- Text content (using --content or from stdin)

For Nostr events:
  <info>%command.full_name% --event-id <event_id> --content "Highlighted text"</info>
  <info>%command.full_name% --event-id <event_id> --comment "My thoughts on this highlight"</info>

For URLs:
  <info>%command.full_name% --url <url> --content "Highlighted text"</info>
  <info>%command.full_name% --url <url> --content "Highlighted text" --context "Surrounding context"</info>

For text content:
  <info>%command.full_name% --content "Text to highlight"</info>
  <info>cat file.txt | %command.full_name%</info>

Additional options:
- Add author attribution:
  <info>%command.full_name% --author <pubkey> --role author</info>
  <info>%command.full_name% --author <pubkey> --role editor</info>

- Add context for partial highlights:
  <info>%command.full_name% --context "Surrounding text"</info>

- Create a quote highlight with comment:
  <info>%command.full_name% --comment "My thoughts on this highlight"</info>

The command follows NIP-84 for highlight events (kind 9802).
HELP
            )
            ->addArgument('content', InputArgument::OPTIONAL, 'Content to highlight (if not using --content)')
            ->addOption('event-id', 'e', InputOption::VALUE_REQUIRED, 'Event ID to highlight')
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'URL to highlight')
            ->addOption('content', 'c', InputOption::VALUE_REQUIRED, 'Highlighted text content')
            ->addOption('context', null, InputOption::VALUE_REQUIRED, 'Surrounding context for the highlight')
            ->addOption('comment', null, InputOption::VALUE_REQUIRED, 'Comment for quote highlight')
            ->addOption('author', 'a', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Author pubkey to attribute')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Role for author attribution (author|editor)')
            ->addOption('relay', 'r', InputOption::VALUE_REQUIRED, 'Relay URL to publish to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $relay = $input->getOption('relay') ?? 'wss://relay.damus.io';
        $json = $input->getOption('json');

        // Get content from argument, option, or stdin
        $content = $input->getArgument('content') ?? 
                  $input->getOption('content') ?? 
                  $this->getStdinContent();

        if (empty($content) && !$input->getOption('event-id') && !$input->getOption('url')) {
            $io->error('No content provided. Use --content, --event-id, --url, or provide content as argument.');
            return Command::FAILURE;
        }

        try {
            $tags = [];

            // Add event reference
            if ($eventId = $input->getOption('event-id')) {
                $tags[] = ['e', $eventId, $relay];
            }

            // Add URL reference
            if ($url = $input->getOption('url')) {
                $tags[] = ['r', $this->cleanUrl($url), 'source'];
            }

            // Add context
            if ($context = $input->getOption('context')) {
                $tags[] = ['context', $context];
            }

            // Add comment for quote highlight
            if ($comment = $input->getOption('comment')) {
                $tags[] = ['comment', $comment];
            }

            // Add author attributions
            foreach ($input->getOption('author') as $author) {
                $role = $input->getOption('role') ?? 'author';
                $tags[] = ['p', $author, $relay, $role];
            }

            $event = $this->nostrService->createEvent(
                kind: 9802,
                content: $content,
                tags: $tags
            );

            $this->nostrService->publishEvent($event, $relay);
            $this->logger->info('Published highlight', [
                'event_id' => $event->getId(),
                'relay' => $relay,
            ]);

            if ($json) {
                $io->writeln(json_encode($event, JSON_PRETTY_PRINT));
            } else {
                $io->success('Highlight published successfully');
                $io->writeln('Event ID: ' . $event->getId());
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish highlight', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $io->error('Failed to publish highlight: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getStdinContent(): ?string
    {
        if (posix_isatty(STDIN)) {
            return null;
        }

        $content = stream_get_contents(STDIN);
        return $content ?: null;
    }

    private function cleanUrl(string $url): string
    {
        // Remove common tracking parameters
        $params = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'gclid', 'msclkid', 'dclid', 'yclid',
            'igshid', 'twclid', 'snapid',
        ];

        $parsed = parse_url($url);
        if (!isset($parsed['query'])) {
            return $url;
        }

        parse_str($parsed['query'], $query);
        foreach ($params as $param) {
            unset($query[$param]);
        }

        $parsed['query'] = http_build_query($query);
        return $this->buildUrl($parsed);
    }

    private function buildUrl(array $parts): string
    {
        $url = '';
        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
    }
} 
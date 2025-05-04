<?php

namespace App\Command\Trait;

use App\Service\NostrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait FeedCommandTrait
{
    protected function displayThreads(
        SymfonyStyle $io,
        array $threads,
        NostrService $nostrService,
        string $relay,
        bool $json = false,
        bool $verbose = false
    ): void {
        if ($json) {
            $io->writeln(json_encode($threads, JSON_PRETTY_PRINT));
            return;
        }

        foreach ($threads as $rootId => $thread) {
            $root = $thread[0];
            $io->section(sprintf(
                '[%s] %s by %s (ID: %s)',
                $this->getKindName($root['kind']),
                $this->getEventTitle($root),
                $root['author'],
                $root['id']
            ));

            if ($root['status']) {
                $io->text(sprintf('Status: %s', $root['status']));
            }

            if ($verbose) {
                $io->text(sprintf('Created: %s', date('Y-m-d H:i:s', $root['created_at'])));
                $io->text(sprintf('Kind: %d', $root['kind']));
                $io->text('Tags:');
                foreach ($root['tags'] as $tag) {
                    $io->text(sprintf('  - %s', implode(':', $tag)));
                }
            }

            $io->text($this->truncateContent($root['content']));
            $io->newLine();

            // Display replies
            foreach (array_slice($thread, 1) as $reply) {
                $io->text(sprintf(
                    '  ↳ %s (ID: %s): %s',
                    $reply['author'],
                    $reply['id'],
                    $this->truncateContent($reply['content'])
                ));

                if ($verbose) {
                    $io->text(sprintf('    Created: %s', date('Y-m-d H:i:s', $reply['created_at'])));
                    $io->text(sprintf('    Kind: %d', $reply['kind']));
                }
            }

            $io->newLine();
        }
    }

    protected function organizeThreads(
        array $events,
        NostrService $nostrService,
        string $relay,
        ?LoggerInterface $logger = null
    ): array {
        $threads = [];
        foreach ($events as $event) {
            try {
                $kind = $event['kind'];
                $id = $event['id'];
                $pubkey = $event['pubkey'];
                $content = $event['content'];
                $tags = $event['tags'];
                $createdAt = $event['created_at'];

                // Get author name
                $authorName = $nostrService->getAuthorName($pubkey, $relay) ?? $pubkey;

                // Get reply-to event if any
                $replyTo = null;
                foreach ($tags as $tag) {
                    if ($tag[0] === 'e' && isset($tag[3]) && $tag[3] === 'reply') {
                        $replyTo = $tag[1];
                        break;
                    }
                }

                $eventData = [
                    'id' => $id,
                    'kind' => $kind,
                    'author' => $authorName,
                    'content' => $content,
                    'created_at' => $createdAt,
                    'tags' => $tags,
                    'replies' => [],
                ];

                if ($replyTo) {
                    if (!isset($threads[$replyTo])) {
                        $threads[$replyTo] = [];
                    }
                    $threads[$replyTo][] = $eventData;
                } else {
                    if (!isset($threads[$id])) {
                        $threads[$id] = [];
                    }
                    $threads[$id][] = $eventData;
                }

                if ($logger) {
                    $logger->debug('Processed event', [
                        'id' => $id,
                        'kind' => $kind,
                        'author' => $authorName,
                        'reply_to' => $replyTo,
                    ]);
                }
            } catch (\Exception $e) {
                if ($logger) {
                    $logger->error('Failed to process event', [
                        'error' => $e->getMessage(),
                        'event' => $event,
                    ]);
                }
                continue;
            }
        }

        return $threads;
    }

    protected function truncateContent(string $content, int $length = 100): string
    {
        if (strlen($content) <= $length) {
            return $content;
        }
        return substr($content, 0, $length) . '...';
    }

    protected function getEventTitle(array $event): string
    {
        foreach ($event['tags'] as $tag) {
            if ($tag[0] === 'subject') {
                return $tag[1];
            }
            if ($tag[0] === 'name') {
                return $tag[1];
            }
        }
        return $this->truncateContent($event['content'], 50);
    }

    protected function getEventStatus(array $event, array $events): ?string
    {
        $statusEvents = array_filter($events, function ($e) use ($event) {
            if (!in_array($e['kind'], [1630, 1631, 1632, 1633])) {
                return false;
            }
            foreach ($e['tags'] as $tag) {
                if ($tag[0] === 'e' && $tag[1] === $event['id']) {
                    return true;
                }
            }
            return false;
        });

        if (empty($statusEvents)) {
            return null;
        }

        // Get the most recent status
        usort($statusEvents, function ($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        $latestStatus = $statusEvents[0];
        return match ($latestStatus['kind']) {
            1630 => 'Open',
            1631 => 'Applied/Merged',
            1632 => 'Closed',
            1633 => 'Draft',
            default => null,
        };
    }
} 
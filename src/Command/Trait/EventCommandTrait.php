<?php

namespace Sybil\Command\Trait;

use Sybil\Service\NostrEventService;
use Sybil\Exception\CommandException;

/**
 * Trait providing event-related functionality
 */
trait EventCommandTrait
{
    protected NostrEventService $eventService;

    /**
     * Create a test event
     */
    protected function createTestEvent(string $content = 'Test event'): array
    {
        return [
            'kind' => 1,
            'content' => $content,
            'tags' => [['t', 'test']],
            'created_at' => time(),
        ];
    }

    /**
     * Test event publishing
     */
    protected function testEventPublishing(string $relayUrl): array
    {
        $start = microtime(true);
        $status = false;
        $message = '';

        try {
            $event = $this->createTestEvent();
            $status = $this->eventService->publishEvent($event, $relayUrl);
            $message = $status ? 'Successfully published test event' : 'Failed to publish test event';
            $this->logger->debug('Publishing test result', [
                'url' => $relayUrl,
                'status' => $status,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            $message = sprintf('Error: %s', $e->getMessage());
            $this->logger->error('Publishing test failed', [
                'url' => $relayUrl,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'name' => 'Event Publishing',
            'status' => $status,
            'message' => $message,
            'duration' => round(microtime(true) - $start, 2),
        ];
    }

    /**
     * Test event querying
     */
    protected function testEventQuerying(string $relayUrl): array
    {
        $start = microtime(true);
        $status = false;
        $message = '';
        $events = [];

        try {
            $events = $this->eventService->queryEvents(['limit' => 1], $relayUrl);
            $status = !empty($events);
            $message = $status ? 'Successfully queried events' : 'No events found';
            $this->logger->debug('Querying test result', [
                'url' => $relayUrl,
                'status' => $status,
                'message' => $message,
                'count' => count($events)
            ]);
        } catch (\Exception $e) {
            $message = sprintf('Error: %s', $e->getMessage());
            $this->logger->error('Querying test failed', [
                'url' => $relayUrl,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'name' => 'Event Querying',
            'status' => $status,
            'message' => $message,
            'events' => $events,
            'duration' => round(microtime(true) - $start, 2),
        ];
    }

    /**
     * Format event for display
     */
    protected function formatEvent(array $event): string
    {
        $lines = [];
        $lines[] = sprintf("ID: %s", $event['id'] ?? 'N/A');
        $lines[] = sprintf("Kind: %d", $event['kind'] ?? 0);
        $lines[] = sprintf("Content: %s", $event['content'] ?? '');
        $lines[] = sprintf("Created: %s", date('Y-m-d H:i:s', $event['created_at'] ?? 0));
        
        if (!empty($event['tags'])) {
            $lines[] = "Tags:";
            foreach ($event['tags'] as $tag) {
                $lines[] = sprintf("  - %s", implode(', ', $tag));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Validate event data
     */
    protected function validateEvent(array $event): void
    {
        $required = ['kind', 'content', 'created_at'];
        foreach ($required as $field) {
            if (!isset($event[$field])) {
                throw new CommandException(
                    sprintf('Missing required event field: %s', $field),
                    CommandException::INVALID_ARGUMENT,
                    ['field' => $field]
                );
            }
        }
    }
} 
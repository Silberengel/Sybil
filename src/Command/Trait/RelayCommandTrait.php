<?php

namespace Sybil\Command\Trait;

use Sybil\Utility\RelayUtility;
use Sybil\Exception\CommandException;

/**
 * Trait providing relay-related functionality
 */
trait RelayCommandTrait
{
    /**
     * Validate a relay URL
     */
    protected function validateRelayUrl(string $url, string $protocol = 'ws'): bool
    {
        if ($protocol === 'http') {
            return preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(?::[0-9]+)?(?:\/[^\s]*)?$/', $url) === 1;
        }
        return preg_match('/^wss?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(?::[0-9]+)?(?:\/[^\s]*)?$/', $url) === 1;
    }

    /**
     * Convert relay URL to specified protocol
     */
    protected function convertRelayUrl(string $url, string $protocol = 'ws'): string
    {
        if ($protocol === 'http') {
            return preg_replace('/^wss?:\/\//', 'https://', $url);
        }
        return preg_replace('/^https?:\/\//', 'wss://', $url);
    }

    /**
     * Test relay connectivity
     */
    protected function testRelayConnectivity(string $relayUrl, string $protocol = 'ws'): array
    {
        $start = microtime(true);
        $status = false;
        $message = '';

        try {
            $status = RelayUtility::testConnection($relayUrl, $protocol);
            $message = $status ? 'Successfully connected to relay' : 'Failed to connect to relay';
            $this->logger->debug('Connectivity test result', [
                'url' => $relayUrl,
                'protocol' => $protocol,
                'status' => $status,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            $message = sprintf('Error: %s', $e->getMessage());
            $this->logger->error('Connectivity test failed', [
                'url' => $relayUrl,
                'protocol' => $protocol,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'name' => 'Basic Connectivity',
            'status' => $status,
            'message' => $message,
            'duration' => round(microtime(true) - $start, 2),
        ];
    }

    /**
     * Get relay NIP-11 information
     */
    protected function getRelayInfo(string $relayUrl, string $protocol = 'ws'): array
    {
        try {
            return RelayUtility::getRelayInfo($relayUrl, $protocol);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get relay info', [
                'url' => $relayUrl,
                'protocol' => $protocol,
                'error' => $e->getMessage()
            ]);
            throw new CommandException('Failed to get relay info: ' . $e->getMessage());
        }
    }

    /**
     * Format relay test results
     */
    protected function formatRelayTestResults(array $results, OutputInterface $output): void
    {
        $output->writeln(sprintf("\n<info>Relay Test Results for %s</info>", $results['relay_url']));
        $output->writeln(str_repeat('-', 80));

        foreach ($results['tests'] as $test) {
            $status = $test['status'] ? '<info>✓</info>' : '<error>✗</error>';
            $output->writeln(sprintf(
                "%s %s (%s)",
                $status,
                $test['name'],
                $this->formatDuration($test['duration'])
            ));
            $output->writeln(sprintf("    %s", $test['message']));

            if (isset($test['info']) && $test['info']) {
                $output->writeln("\n    <comment>NIP-11 Information:</comment>");
                foreach ($test['info'] as $key => $value) {
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $output->writeln(sprintf("    %s: %s", $key, $value));
                }
            }
            $output->writeln('');
        }

        $output->writeln(sprintf(
            "Overall Status: %s (Duration: %s)",
            $results['overall_status'] ? '<info>Success</info>' : '<error>Failed</error>',
            $this->formatDuration($results['duration'])
        ));
    }

    /**
     * Validate relay URL and throw exception if invalid
     */
    protected function validateRelayUrlOrFail(string $url): void
    {
        if (!$this->validateRelayUrl($url)) {
            throw new CommandException(
                'Invalid relay URL. Must start with ws:// or wss://',
                CommandException::INVALID_ARGUMENT,
                ['url' => $url]
            );
        }
    }
} 
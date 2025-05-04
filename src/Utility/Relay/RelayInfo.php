<?php

namespace Sybil\Utility\Relay;

use Sybil\Service\RelayQueryService;
use Sybil\Exception\RelayException;
use Psr\Log\LoggerInterface;

/**
 * Utility class for working with relay information
 * 
 * This class provides utility functions for working with relay information,
 * including validation and formatting.
 */
class RelayInfoUtility
{
    private RelayQueryService $relayQueryService;
    private LoggerInterface $logger;

    public function __construct(
        RelayQueryService $relayQueryService,
        LoggerInterface $logger
    ) {
        $this->relayQueryService = $relayQueryService;
        $this->logger = $logger;
    }

    /**
     * Get relay information using NIP-11
     *
     * @param string $relayUrl The relay URL
     * @param bool $debug Whether to show debug information
     * @return array|null The relay information or null if failed
     */
    public function getInfo(string $relayUrl, bool $debug = false): ?array
    {
        try {
            $this->logger->debug('Fetching relay information', [
                'relay' => $relayUrl
            ]);

            $info = $this->relayQueryService->getRelayInfo($relayUrl);
            
            if ($debug) {
                $this->logger->debug('Relay information', [
                    'relay' => $relayUrl,
                    'info' => $info
                ]);
            }

            return $info;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch relay information', [
                'relay' => $relayUrl,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if a relay supports a specific NIP
     *
     * @param string $relayUrl The relay URL
     * @param int $nip The NIP number to check
     * @return bool Whether the relay supports the NIP
     */
    public function supportsNip(string $relayUrl, int $nip): bool
    {
        try {
            $info = $this->getInfo($relayUrl);
            if (!$info) {
                return false;
            }

            $supportedNips = $info['supported_nips'] ?? [];
            return in_array($nip, $supportedNips);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to check NIP support', [
                'relay' => $relayUrl,
                'nip' => $nip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Test relay connectivity
     *
     * @param string $relayUrl The relay URL
     * @return bool Whether the relay is accessible
     */
    public function testConnectivity(string $relayUrl): bool
    {
        try {
            $this->logger->debug('Testing relay connectivity', [
                'relay' => $relayUrl
            ]);

            $info = $this->getInfo($relayUrl);
            return $info !== null;
        } catch (\Exception $e) {
            $this->logger->warning('Relay connectivity test failed', [
                'relay' => $relayUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get relay limits
     *
     * @param string $relayUrl The relay URL
     * @return array The relay limits
     */
    public function getLimits(string $relayUrl): array
    {
        try {
            $info = $this->getInfo($relayUrl);
            if (!$info) {
                return [];
            }

            return [
                'max_message_length' => $info['max_message_length'] ?? null,
                'max_subscriptions' => $info['max_subscriptions'] ?? null,
                'max_filters' => $info['max_filters'] ?? null,
                'max_limit' => $info['max_limit'] ?? null,
                'max_subid_length' => $info['max_subid_length'] ?? null,
                'min_prefix' => $info['min_prefix'] ?? null,
                'max_event_tags' => $info['max_event_tags'] ?? null,
                'max_content_length' => $info['max_content_length'] ?? null,
                'min_pow_difficulty' => $info['min_pow_difficulty'] ?? null,
                'auth_required' => $info['auth_required'] ?? false,
                'payment_required' => $info['payment_required'] ?? false
            ];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get relay limits', [
                'relay' => $relayUrl,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
} 
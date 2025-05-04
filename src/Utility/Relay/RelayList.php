<?php

namespace Sybil\Utility\Relay;

use Sybil\Exception\RelayException;
use Sybil\Service\RelayQueryService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Utility class for working with relay lists
 * 
 * This class provides utility functions for working with relay lists,
 * including validation and management.
 */
class RelayListUtility
{
    private RelayQueryService $relayQueryService;
    private RelayInfo $relayInfo;
    private LoggerInterface $logger;
    private array $relayConfig;

    public function __construct(
        RelayQueryService $relayQueryService,
        RelayInfo $relayInfo,
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->relayQueryService = $relayQueryService;
        $this->relayInfo = $relayInfo;
        $this->logger = $logger;
        $this->relayConfig = require __DIR__ . '/../../../config/relays.php';
    }

    /**
     * Get the list of relays for a specific event kind
     *
     * @param int $kind The event kind
     * @param array $preferredRelays Optional array of preferred relay URLs
     * @param bool $authenticate Whether to authenticate with the relays
     * @return array The list of relay URLs
     */
    public function getRelayList(int $kind = 0, array $preferredRelays = [], bool $authenticate = false): array
    {
        try {
            $this->logger->debug('Getting relay list', [
                'kind' => $kind,
                'preferred_count' => count($preferredRelays)
            ]);

            // Get relays from configuration
            $relays = $this->getRelaysFromConfig($kind);
            
            // Add preferred relays if specified
            if (!empty($preferredRelays)) {
                $relays = array_merge($preferredRelays, $relays);
                $relays = array_unique($relays);
            }

            // Filter relays by connectivity
            $relays = $this->filterByConnectivity($relays);

            // Filter relays by NIP support if needed
            if ($authenticate) {
                $relays = $this->filterByNipSupport($relays, 42); // NIP-42 for authentication
            }

            $this->logger->info('Retrieved relay list', [
                'kind' => $kind,
                'count' => count($relays)
            ]);

            return $relays;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get relay list', [
                'kind' => $kind,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get default relays for a specific event kind
     *
     * @param int $kind The event kind
     * @return array The list of default relay URLs
     */
    public function getDefaultRelays(int $kind = 0): array
    {
        // Get the main relay list
        $relays = $this->relayConfig['relays'] ?? [];
        
        // If no relays are configured, fall back to default_ws
        if (empty($relays)) {
            return [$this->relayConfig['default_ws']];
        }
        
        return $relays;
    }

    /**
     * Filter relays by connectivity
     *
     * @param array $relays The relay URLs to filter
     * @return array The accessible relays
     */
    private function filterByConnectivity(array $relays): array
    {
        $this->logger->debug('Filtering relays by connectivity', [
            'total' => count($relays)
        ]);

        $accessibleRelays = [];
        foreach ($relays as $relay) {
            if ($this->relayInfo->testConnectivity($relay)) {
                $accessibleRelays[] = $relay;
            }
        }

        $this->logger->info('Filtered relays by connectivity', [
            'total' => count($relays),
            'accessible' => count($accessibleRelays)
        ]);

        return $accessibleRelays;
    }

    /**
     * Filter relays by NIP support
     *
     * @param array $relays The relay URLs to filter
     * @param int $nip The NIP number to check for
     * @return array The relays supporting the NIP
     */
    private function filterByNipSupport(array $relays, int $nip): array
    {
        $this->logger->debug('Filtering relays by NIP support', [
            'nip' => $nip,
            'total' => count($relays)
        ]);

        $supportedRelays = [];
        foreach ($relays as $relay) {
            if ($this->relayInfo->supportsNip($relay, $nip)) {
                $supportedRelays[] = $relay;
            }
        }

        $this->logger->info('Filtered relays by NIP support', [
            'nip' => $nip,
            'total' => count($relays),
            'supported' => count($supportedRelays)
        ]);

        return $supportedRelays;
    }

    /**
     * Get relays from configuration
     *
     * @param int $kind The event kind
     * @return array The list of relay URLs from configuration
     */
    private function getRelaysFromConfig(int $kind): array
    {
        try {
            // Get the main relay list
            $relays = $this->relayConfig['relays'] ?? [];
            
            // If no relays are configured, fall back to default_ws
            if (empty($relays)) {
                return [$this->relayConfig['default_ws']];
            }
            
            return $relays;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get relays from configuration', [
                'kind' => $kind,
                'error' => $e->getMessage()
            ]);
            return [$this->relayConfig['default_ws']];
        }
    }

    /**
     * Sort relays by response time
     *
     * @param array $relays The relay URLs to sort
     * @return array The sorted relays
     */
    public function sortByResponseTime(array $relays): array
    {
        $this->logger->debug("Sorting relays by response time", [
            'total' => count($relays)
        ]);

        $responseTimes = [];
        foreach ($relays as $relay) {
            $start = microtime(true);
            try {
                $this->relayQueryService->getRelayInfo($relay);
                $responseTimes[$relay] = microtime(true) - $start;
            } catch (\Exception $e) {
                $responseTimes[$relay] = PHP_FLOAT_MAX;
            }
        }

        asort($responseTimes);
        
        $sortedRelays = array_keys($responseTimes);

        $this->logger->info("Sorted relays by response time", [
            'total' => count($relays)
        ]);

        return $sortedRelays;
    }

    /**
     * Validate relay URLs
     *
     * @param array $relays The relay URLs to validate
     * @return array The valid relays
     */
    public function validateUrls(array $relays): array
    {
        $this->logger->debug("Validating relay URLs", [
            'total' => count($relays)
        ]);

        $validRelays = array_filter($relays, function($relay) {
            return $this->isValidUrl($relay);
        });

        $this->logger->info("Validated relay URLs", [
            'total' => count($relays),
            'valid' => count($validRelays)
        ]);

        return array_values($validRelays);
    }

    /**
     * Check if a relay URL is valid
     *
     * @param string $relay The relay URL to validate
     * @return bool Whether the URL is valid
     */
    public function isValidUrl(string $relay): bool
    {
        $pattern = '/^wss?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}(:[0-9]+)?(\/\S*)?$/';
        return (bool) preg_match($pattern, $relay);
    }

    /**
     * Remove duplicate relays
     *
     * @param array $relays The relay URLs to deduplicate
     * @return array The unique relays
     */
    public function removeDuplicates(array $relays): array
    {
        $this->logger->debug("Removing duplicate relays", [
            'total' => count($relays)
        ]);

        $uniqueRelays = array_unique($relays);

        $this->logger->info("Removed duplicate relays", [
            'total' => count($relays),
            'unique' => count($uniqueRelays)
        ]);

        return array_values($uniqueRelays);
    }
} 
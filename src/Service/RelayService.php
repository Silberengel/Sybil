<?php

namespace Sybil\Service;

use swentel\nostr\Relay\Relay;

/**
 * Service for managing relays
 * 
 * This service handles relay-related functionality, such as getting relay lists
 * and creating relay objects.
 */
class RelayService
{
    /**
     * @var array Relay configuration
     */
    private array $config;
    
    /**
     * Constructor
     *
     * @param array $config Relay configuration
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Get a list of relay objects
     *
     * @param int $kind Event kind
     * @param array $preferredRelays Optional array of preferred relay URLs
     * @return array Array of Relay objects
     */
    public function getRelayList(int $kind = 0, array $preferredRelays = []): array
    {
        // If preferred relays are provided, use them first
        if (!empty($preferredRelays)) {
            return $this->createRelayObjects($preferredRelays);
        }
        
        // Use the appropriate default relay based on event kind
        $defaultRelay = ($kind === 1) ? $this->config['kind1_default'] : $this->config['default'];
        
        // Read relay list from user configuration file
        $relayUrls = $this->readUserRelayFile();
        
        // Use default if empty
        if (empty($relayUrls)) {
            $relayUrls = [$defaultRelay];
        }
        
        return $this->createRelayObjects($relayUrls);
    }
    
    /**
     * Get the default relays for a specific event kind
     *
     * @param int $kind Event kind
     * @return array Array of Relay objects
     */
    public function getDefaultRelays(int $kind = 0): array
    {
        $relayUrls = ($kind === 1) ? $this->config['kind1_relays'] : $this->config['relays'];
        return $this->createRelayObjects($relayUrls);
    }
    
    /**
     * Create relay objects from URLs
     *
     * @param array $urls Array of relay URLs
     * @return array Array of Relay objects
     */
    private function createRelayObjects(array $urls): array
    {
        $relays = [];
        foreach ($urls as $url) {
            $relays[] = new Relay(websocket: $url);
        }
        return $relays;
    }
    
    /**
     * Read the user's relay configuration file
     *
     * @return array Array of relay URLs
     */
    private function readUserRelayFile(): array
    {
        $relaysFile = $this->config['user_relays_file'];
        
        if (!file_exists($relaysFile)) {
            return [];
        }
        
        return file($relaysFile, FILE_IGNORE_NEW_LINES);
    }
}

<?php

namespace Sybil\Service;

use swentel\nostr\Relay\Relay;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service for managing relays
 * 
 * This service handles relay-related functionality, such as getting relay lists,
 * creating relay objects, and managing relay configuration.
 * 
 * @package Sybil\Service
 */
class RelayService
{
    /**
     * @var array Relay configuration
     */
    private array $config;
    
    /**
     * @var array Cache for relay lists
     */
    private array $relayCache = [];
    
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
    /**
     * Constructor
     *
     * @param array $config Relay configuration
     * @param LoggerService $logger Logger service
     */
    public function __construct(array $config, LoggerService $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Get a list of relay objects
     *
     * @param int $kind Event kind
     * @param array $preferredRelays Optional array of preferred relay URLs
     * @return array Array of Relay objects
     * @throws InvalidArgumentException If relay configuration is invalid
     */
    public function getRelayList(int $kind = 0, array $preferredRelays = []): array
    {
        $cacheKey = "{$kind}_" . md5(json_encode($preferredRelays));
        if (isset($this->relayCache[$cacheKey])) {
            return $this->relayCache[$cacheKey];
        }
        
        // If preferred relays are provided, use them first
        if (!empty($preferredRelays)) {
            $relays = $this->createRelayObjects($preferredRelays);
            $this->relayCache[$cacheKey] = $relays;
            return $relays;
        }
        
        // Use the appropriate default relay based on event kind
        $defaultRelay = ($kind === 1) ? $this->config['kind1_default'] : $this->config['default'];
        if (!$this->validateRelayUrl($defaultRelay)) {
            throw new InvalidArgumentException("Invalid default relay URL: $defaultRelay");
        }
        
        // Read relay list from user configuration file
        $relayUrls = $this->readUserRelayFile();
        
        // Use default if empty
        if (empty($relayUrls)) {
            $relayUrls = [$defaultRelay];
        }
        
        $relays = $this->createRelayObjects($relayUrls);
        $this->relayCache[$cacheKey] = $relays;
        return $relays;
    }
    
    /**
     * Get the default relays for a specific event kind
     *
     * @param int $kind Event kind
     * @return array Array of Relay objects
     * @throws InvalidArgumentException If relay configuration is invalid
     */
    public function getDefaultRelays(int $kind = 0): array
    {
        $cacheKey = "default_{$kind}";
        if (isset($this->relayCache[$cacheKey])) {
            return $this->relayCache[$cacheKey];
        }
        
        $relayUrls = ($kind === 1) ? $this->config['kind1_relays'] : $this->config['relays'];
        if (!is_array($relayUrls)) {
            throw new InvalidArgumentException("Invalid relay configuration for kind $kind");
        }
        
        $relays = $this->createRelayObjects($relayUrls);
        $this->relayCache[$cacheKey] = $relays;
        return $relays;
    }
    
    /**
     * Create relay objects from URLs
     *
     * @param array $urls Array of relay URLs
     * @return array Array of Relay objects
     * @throws InvalidArgumentException If any relay URL is invalid
     */
    private function createRelayObjects(array $urls): array
    {
        $relays = [];
        foreach ($urls as $url) {
            if (!$this->validateRelayUrl($url)) {
                throw new InvalidArgumentException("Invalid relay URL: $url");
            }
            $relays[] = new Relay(websocket: $url);
        }
        return $relays;
    }
    
    /**
     * Read the user's relay configuration file
     *
     * @return array Array of relay URLs
     * @throws RuntimeException If the relay file cannot be read
     */
    private function readUserRelayFile(): array
    {
        $relaysFile = $this->config['user_relays_file'];
        
        if (!file_exists($relaysFile)) {
            $this->logger->debug("Relay configuration file not found: $relaysFile");
            return [];
        }
        
        $content = file($relaysFile, FILE_IGNORE_NEW_LINES);
        if ($content === false) {
            throw new RuntimeException("Failed to read relay configuration file: $relaysFile");
        }
        
        // Filter out empty lines and comments
        return array_filter($content, function($line) {
            $line = trim($line);
            return !empty($line) && !str_starts_with($line, '#');
        });
    }
    
    /**
     * Validate a relay URL
     *
     * @param string $url The relay URL to validate
     * @return bool True if valid, false otherwise
     */
    private function validateRelayUrl(string $url): bool
    {
        // Check if URL starts with ws:// or wss://
        if (!preg_match('/^wss?:\/\//', $url)) {
            return false;
        }
        
        // Check if URL is well-formed
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['host'])) {
            return false;
        }
        
        // Check if host is not empty
        if (empty($parsedUrl['host'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Test relay connectivity
     *
     * @param string $url The relay URL to test
     * @param int $timeout Connection timeout in seconds
     * @return bool True if the relay is reachable, false otherwise
     */
    public function testRelay(string $url, int $timeout = 5): bool
    {
        if (!$this->validateRelayUrl($url)) {
            $this->logger->error("Invalid relay URL: $url");
            return false;
        }
        
        try {
            $relay = new Relay(websocket: $url);
            $relay->setTimeout($timeout);
            $relay->connect();
            $relay->disconnect();
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to connect to relay $url: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add a relay to the user's configuration
     *
     * @param string $url The relay URL to add
     * @return bool True if successful, false otherwise
     */
    public function addRelay(string $url): bool
    {
        if (!$this->validateRelayUrl($url)) {
            $this->logger->error("Invalid relay URL: $url");
            return false;
        }
        
        $relaysFile = $this->config['user_relays_file'];
        $relays = $this->readUserRelayFile();
        
        // Check if relay already exists
        if (in_array($url, $relays)) {
            $this->logger->warning("Relay already exists in configuration: $url");
            return true;
        }
        
        // Add the new relay
        $relays[] = $url;
        
        // Write back to file
        $content = implode(PHP_EOL, $relays) . PHP_EOL;
        if (file_put_contents($relaysFile, $content) === false) {
            $this->logger->error("Failed to write relay configuration file: $relaysFile");
            return false;
        }
        
        // Clear cache
        $this->relayCache = [];
        
        return true;
    }
    
    /**
     * Remove a relay from the user's configuration
     *
     * @param string $url The relay URL to remove
     * @return bool True if successful, false otherwise
     */
    public function removeRelay(string $url): bool
    {
        if (!$this->validateRelayUrl($url)) {
            $this->logger->error("Invalid relay URL: $url");
            return false;
        }
        
        $relaysFile = $this->config['user_relays_file'];
        $relays = $this->readUserRelayFile();
        
        // Check if relay exists
        if (!in_array($url, $relays)) {
            $this->logger->warning("Relay not found in configuration: $url");
            return true;
        }
        
        // Remove the relay
        $relays = array_diff($relays, [$url]);
        
        // Write back to file
        $content = implode(PHP_EOL, $relays) . PHP_EOL;
        if (file_put_contents($relaysFile, $content) === false) {
            $this->logger->error("Failed to write relay configuration file: $relaysFile");
            return false;
        }
        
        // Clear cache
        $this->relayCache = [];
        
        return true;
    }
}

<?php
/**
 * Class RelayUtility
 * 
 * This class provides utility functions for working with Nostr relays:
 * - Getting relay lists
 * - Managing relay connections
 * - Sending events to relays
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\RelayUtility;
 * use swentel\nostr\Message\EventMessage;
 * 
 * // Get a relay list for a specific event kind
 * $relays = RelayUtility::getRelayList(1);
 * 
 * // Get default relays for a specific event kind
 * $defaultRelays = RelayUtility::getDefaultRelays(1);
 * 
 * // Send an event with retry
 * $eventMessage = new EventMessage($event);
 * $result = RelayUtility::sendEventWithRetry($eventMessage, $relays);
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see EventUtility For event-related operations
 * @see RequestUtility For request-related operations
 * @see ErrorHandlingUtility For error handling operations
 */

namespace Sybil\Utilities;

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Message\AuthMessage;
use Sybil\Service\LoggerService;

class RelayUtility
{
    // Constants
    public const DEFAULT_RELAY_KIND1 = 'wss://freelay.sovbit.host';
    public const DEFAULT_RELAY_OTHER = 'wss://thecitadel.nostr1.com';
    
    private static ?LoggerService $logger = null;

    /**
     * Get the logger instance
     *
     * @return LoggerService
     */
    private static function getLogger(): LoggerService
    {
        if (self::$logger === null) {
            self::$logger = new LoggerService();
        }
        return self::$logger;
    }

    /**
     * Set the logger instance
     *
     * @param LoggerService $logger
     */
    public static function setLogger(LoggerService $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Authenticates with a relay using NIP-42
     *
     * @param Relay $relay The relay to authenticate with
     * @param string $privateKey The private key to use for authentication
     * @return bool Whether authentication was successful
     */
    public static function authenticateWithRelay(Relay $relay, string $privateKey): bool
    {
        try {
            $authMessage = new AuthMessage($privateKey);
            $relay->setMessage($authMessage);
            $result = $relay->send();
            return isset($result['auth']) && $result['auth'] === 'OK';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gets the list of relays from the configuration file.
     *
     * @param int $kind Optional event kind to get relays for
     * @param array $preferredRelays Optional array of preferred relay URLs to use if available
     * @param bool $authenticate Whether to authenticate with the relays
     * @return array An array of Relay objects
     */
    public static function getRelayList(int $kind = 0, array $preferredRelays = [], bool $authenticate = false): array
    {
        // If preferred relays are provided, use them first
        if (!empty($preferredRelays)) {
            $relays = [];
            foreach ($preferredRelays as $url) {
                $relay = new Relay(websocket: $url);
                if ($authenticate) {
                    self::authenticateWithRelay($relay, KeyUtility::getNsec());
                }
                $relays[] = $relay;
            }
            return $relays;
        }
        
        // Use sovbit relay as the default for kind 1 notes
        $defaultRelay = ($kind === 1) ? self::DEFAULT_RELAY_KIND1 : self::DEFAULT_RELAY_OTHER;
        $relaysFile = getcwd() . "/user/relays.yml";
        
        // Read relay list from file
        $relayUrls = [];
        if (file_exists($relaysFile)) {
            $relayUrls = file($relaysFile, FILE_IGNORE_NEW_LINES);
        }
        
        // Use default if empty
        if (empty($relayUrls)) {
            $relayUrls = [$defaultRelay];
        }
        
        // Convert URLs to Relay objects
        $relays = [];
        foreach ($relayUrls as $url) {
            $relay = new Relay(websocket: $url);
            if ($authenticate) {
                self::authenticateWithRelay($relay, KeyUtility::getNsec());
            }
            $relays[] = $relay;
        }
        
        return $relays;
    }
    
    /**
     * Gets the default relay list for a specific event kind.
     *
     * @param int $kind The event kind
     * @return array An array of Relay objects
     */
    public static function getDefaultRelays(int $kind = 0): array
    {
        // Use sovbit relay as the default for kind 1 notes
        if ($kind === 1) {
            return [
                new Relay('wss://freelay.sovbit.host'),
                new Relay('wss://relay.damus.io'),
                new Relay('wss://relay.nostr.band'),
                new Relay('wss://nos.lol'),
                new Relay('wss://theforest.nostr1.com'),
                new Relay('ws://localhost:8080')
            ];
        } else {
            return [
                new Relay('wss://thecitadel.nostr1.com'),
                new Relay('wss://relay.damus.io'),
                new Relay('wss://relay.nostr.band'),
                new Relay('wss://nostr.einundzwanzig.space'),
                new Relay('wss://relay.primal.net'),
                new Relay('wss://nos.lol'),
                new Relay('wss://relay.lumina.rocks'),
                new Relay('wss://freelay.sovbit.host'),
                new Relay('wss://wheat.happytavern.co'),
                new Relay('wss://nostr21.com'),
                new Relay('wss://theforest.nostr1.com'),
                new Relay('ws://localhost:8080')
            ];
        }
    }
    
    /**
     * Sends an event with retry on failure.
     *
     * @param EventMessage $eventMessage The event message to send
     * @param array $customRelays Optional array of Relay objects to use instead of the default list
     * @return array The result from sending the event
     */
    public static function sendEventWithRetry(\swentel\nostr\Message\EventMessage $eventMessage, array $customRelays = []): array
    {
        $eventId = $eventMessage->getEvent()->getId();
        $eventKind = $eventMessage->getEvent()->getKind();
        
        $logger = self::getLogger();
        $logger->debug("Debug - Initial Event ID: " . $eventId);
        
        // Get the list of relays to use
        $relays = !empty($customRelays) ? $customRelays : self::getRelayList($eventKind);
        
        $logger->info("Sending event kind " . $eventKind . " to " . count($relays) . " relays...");
        
        // Add a note about deletion events
        if ($eventKind === 5) {
            $logger->info("Note: Deletion events (kind 5) may be rejected by some relays due to relay policies.");
            $logger->info("      Ensure you're using the same private key that created the original event.");
        }
        
        // Send the event to all relays
        $result = RequestUtility::sendToMultipleRelays($relays, $eventMessage);
        
        // Check if any relays accepted the event
        if (!empty($result['successful_relays'])) {
            $logger->info("Event successfully sent to " . count($result['successful_relays']) . " relays:");
            $logger->info("  Accepted by: " . implode(", ", $result['successful_relays']));
            if (!empty($result['failed_relays'])) {
                $logger->warning("  Rejected by: " . implode(", ", $result['failed_relays']));
            }
            return [
                'success' => true,
                'message' => 'Event published successfully',
                'event_id' => $eventId,
                'successful_relays' => $result['successful_relays'],
                'failed_relays' => $result['failed_relays']
            ];
        }
        
        // If no relays accepted the event, try again
        if (empty($result['successful_relays'])) {
            $logger->warning("No relays accepted the event. Retrying...");
            if (!empty($result['failed_relays'])) {
                $logger->warning("  Rejected by: " . implode(", ", $result['failed_relays']));
            }
            
            try {
                // Wait a moment before retrying
                sleep(5);
                
                // Try again with the same relays
                $result = RequestUtility::sendToMultipleRelays($relays, $eventMessage);
                
                // Check if any relays accepted the event this time
                if (!empty($result['successful_relays'])) {
                    $logger->info("Event successfully sent to " . count($result['successful_relays']) . " relays on retry:");
                    $logger->info("  Accepted by: " . implode(", ", $result['successful_relays']));
                    if (!empty($result['failed_relays'])) {
                        $logger->warning("  Rejected by: " . implode(", ", $result['failed_relays']));
                    }
                    return [
                        'success' => true,
                        'message' => 'Event published successfully on retry',
                        'event_id' => $eventId,
                        'successful_relays' => $result['successful_relays'],
                        'failed_relays' => $result['failed_relays']
                    ];
                }
            } catch (\TypeError $e) {
                $logger->warning("Sending to relays did not work. Will be retried.");
            } catch (\Exception $e) {
                $logger->warning("Error sending to relays: " . $e->getMessage() . ". Will be retried.");
            }
            
            // If we still have no success, try the default relay
            $defaultRelay = self::DEFAULT_RELAY_OTHER;
            $logger->info("Trying with default relay: " . $defaultRelay);
            
            try {
                // Create a single relay instance
                $relay = new Relay($defaultRelay);
                
                // Send the event to the default relay
                $result = RequestUtility::sendWithRetry($relay, $eventMessage);
                
                // Check if the default relay accepted the event
                if (isset($result['success']) && $result['success']) {
                    $logger->info("Event successfully sent to default relay:");
                    $logger->info("  Accepted by: " . $defaultRelay);
                    return [
                        'success' => true,
                        'message' => 'Event published successfully to default relay',
                        'event_id' => $eventId,
                        'successful_relays' => [$defaultRelay],
                        'failed_relays' => []
                    ];
                } else {
                    $logger->warning("Default relay did not accept the event.");
                    $logger->warning("  Rejected by: " . $defaultRelay);
                }
            } catch (\Exception $e) {
                $logger->error("All relays including default relay failed with error: " . $e->getMessage());
            }
            
            // If we get here, all attempts failed
            if ($eventKind === 5) {
                $logger->error("Deletion event was rejected by all relays. This could be because:");
                $logger->error("1. The relays don't accept deletion events (kind 5)");
                $logger->error("2. The private key used doesn't match the original event creator");
                $logger->error("3. The event ID might not exist on these relays");
            }
            
            return [
                'success' => false,
                'message' => 'Event could not be published to any relay',
                'event_id' => $eventId,
                'successful_relays' => [],
                'failed_relays' => array_merge($result['failed_relays'] ?? [], [$defaultRelay])
            ];
        }
        
        // If we get here, something unexpected happened
        $logger->error("Deletion event could not be sent to any relay. This could be because:");
        $logger->error("1. The relays don't accept deletion events (kind 5)");
        $logger->error("2. The private key used doesn't match the original event creator");
        $logger->error("3. The event ID might not exist on these relays");
        $logger->error("4. Network or connection issues with the relays");
        
        return [
            'success' => false,
            'message' => 'Event could not be published to any relay',
            'event_id' => $eventId,
            'successful_relays' => [],
            'failed_relays' => $result['failed_relays'] ?? []
        ];
    }

    /**
     * Gets information about a relay using NIP-11
     *
     * @param string $relayUrl The relay URL
     * @param bool $debug Whether to show debug information
     * @return array|null The relay information or null if failed
     */
    public static function getRelayInfo(string $relayUrl, bool $debug = false): ?array
    {
        // Convert ws:// or wss:// to http:// or https:// for the info endpoint
        $baseUrl = str_replace(['wss://', 'ws://'], ['https://', 'http://'], $relayUrl);
        
        // Try multiple possible NIP-11 endpoints
        $endpoints = [
            '/.well-known/nostr.json',
            '/api/nip11',
            '/api/info',
            '/info'
        ];
        
        foreach ($endpoints as $endpoint) {
            $url = $baseUrl . $endpoint;
            if ($debug) {
                self::getLogger()->debug("Trying endpoint: {$url}");
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['Accept: application/nostr+json'],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
            $dnsTime = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
            $sslTime = curl_getinfo($ch, CURLINFO_APPCONNECT_TIME);
            $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
            
            curl_close($ch);
            
            if ($debug) {
                self::getLogger()->debug("Connection Details:");
                self::getLogger()->debug("  HTTP Code: {$httpCode}");
                self::getLogger()->debug("  Content-Type: {$contentType}");
                self::getLogger()->debug("  Response Size: {$size} bytes");
                self::getLogger()->debug("  Redirect Count: {$redirectCount}");
                if ($redirectUrl) {
                    self::getLogger()->debug("  Redirect URL: {$redirectUrl}");
                }
                self::getLogger()->debug("\nTiming Information:");
                self::getLogger()->debug("  Total Time: {$totalTime}s");
                self::getLogger()->debug("  DNS Time: {$dnsTime}s");
                self::getLogger()->debug("  Connect Time: {$connectTime}s");
                self::getLogger()->debug("  SSL Time: {$sslTime}s");
                if ($curlError) {
                    self::getLogger()->debug("\ncURL Error: {$curlError}");
                }
                if ($response) {
                    self::getLogger()->debug("\nRaw Response:");
                    self::getLogger()->debug(substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : ''));
                }
            }
            
            if ($response === false) {
                self::getLogger()->warning("Failed to fetch relay info from {$url}: {$curlError}");
                continue;
            }
            
            if ($httpCode !== 200) {
                self::getLogger()->warning("HTTP {$httpCode} from {$url}");
                continue;
            }
            
            $data = json_decode($response, true);
            if ($data === null) {
                self::getLogger()->warning("Invalid JSON response from {$url}");
                continue;
            }
            
            if (!isset($data['name'])) {
                self::getLogger()->warning("Missing required 'name' field in response from {$url}");
                continue;
            }
            
            if ($debug) {
                self::getLogger()->debug("\nParsed Response:");
                self::getLogger()->debug("  Name: " . ($data['name'] ?? 'N/A'));
                self::getLogger()->debug("  Description: " . ($data['description'] ?? 'N/A'));
                self::getLogger()->debug("  Contact: " . ($data['contact'] ?? 'N/A'));
                self::getLogger()->debug("  Software: " . ($data['software'] ?? 'N/A'));
                self::getLogger()->debug("  Version: " . ($data['version'] ?? 'N/A'));
                self::getLogger()->debug("  Supported NIPs: " . implode(', ', $data['supported_nips'] ?? []));
                if (isset($data['limitation'])) {
                    self::getLogger()->debug("\nLimitations:");
                    foreach ($data['limitation'] as $key => $value) {
                        self::getLogger()->debug("  {$key}: " . json_encode($value));
                    }
                }
                if (isset($data['fees'])) {
                    self::getLogger()->debug("\nFees:");
                    foreach ($data['fees'] as $fee) {
                        self::getLogger()->debug("  " . json_encode($fee));
                    }
                }
                if (isset($data['payments_url'])) {
                    self::getLogger()->debug("\nPayments URL: " . $data['payments_url']);
                }
                if (isset($data['icon'])) {
                    self::getLogger()->debug("Icon URL: " . $data['icon']);
                }
            }
            
            return $data;
        }
        
        self::getLogger()->error("Failed to fetch relay info from any endpoint");
        return null;
    }

    /**
     * Checks if a relay supports a specific NIP
     *
     * @param string $relayUrl The relay URL
     * @param int $nip The NIP number to check
     * @return bool Whether the relay supports the NIP
     */
    public static function supportsNip(string $relayUrl, int $nip): bool
    {
        $info = self::getRelayInfo($relayUrl);
        if (!$info || !isset($info['supported_nips'])) {
            return false;
        }
        
        return in_array($nip, $info['supported_nips']);
    }
}

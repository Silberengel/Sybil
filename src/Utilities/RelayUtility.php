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

class RelayUtility
{
    // Constants
    public const DEFAULT_RELAY_KIND1 = 'wss://freelay.sovbit.host';
    public const DEFAULT_RELAY_OTHER = 'wss://thecitadel.nostr1.com';
    
    /**
     * Gets the list of relays from the configuration file.
     *
     * @param int $kind Optional event kind to get relays for
     * @param array $preferredRelays Optional array of preferred relay URLs to use if available
     * @return array An array of Relay objects
     */
    public static function getRelayList(int $kind = 0, array $preferredRelays = []): array
    {
        // If preferred relays are provided, use them first
        if (!empty($preferredRelays)) {
            $relays = [];
            foreach ($preferredRelays as $url) {
                $relays[] = new Relay(websocket: $url);
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
            $relays[] = new Relay(websocket: $url);
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
    public static function sendEventWithRetry(EventMessage $eventMessage, array $customRelays = []): array
    {
        // Get the event kind and ID for better error reporting
        $eventKind = 0;
        $eventId = 'unknown-event-id';
        $eventObj = null;
        
        // Use reflection to access the protected event property
        try {
            $reflection = new \ReflectionClass($eventMessage);
            $properties = $reflection->getProperties();
            
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($eventMessage);
                
                // Check if this property contains the Event object
                if (is_object($value)) {
                    $eventObj = $value;
                    if (method_exists($value, 'getKind')) {
                        $eventKind = $value->getKind();
                    }
                    if (method_exists($value, 'getId')) {
                        $eventId = $value->getId();
                    }
                    break;
                }
            }
        } catch (\Exception $e) {
            // If reflection fails, continue with default values
        }
        
        // Debug output
        echo "Debug - Initial Event ID: " . $eventId . PHP_EOL;
        
        // Use custom relays if provided, otherwise use a default list
        $relays = $customRelays;
        
        // If no custom relays were provided, use a default list
        if (empty($relays)) {
            $relays = self::getDefaultRelays($eventKind);
        }

        // Log the event kind for better debugging
        echo "Sending event kind " . $eventKind . " to " . count($relays) . " relays..." . PHP_EOL;
        
        // For deletion events (kind 5), provide additional information
        if ($eventKind === 5) {
            echo "Note: Deletion events (kind 5) may be rejected by some relays due to relay policies." . PHP_EOL;
            echo "      Ensure you're using the same private key that created the original event." . PHP_EOL;
        }

        $relaySet = new RelaySet();
        $relaySet->setRelays($relays);
        $relaySet->setMessage($eventMessage);

        $maxRetries = 3;
        $retryCount = 0;
        $successfulRelays = [];
        $failedRelays = [];

        while ($retryCount < $maxRetries) {
            try {
                // Use our helper function to handle errors
                $result = \Sybil\Utilities\ErrorHandlingUtility::executeWithErrorHandling(function() use ($relaySet) {
                    return $relaySet->send();
                }, 'RelaySet.php');
                
                // Check if any relays accepted the event
                $anySuccess = false;
                foreach ($result as $relayUrl => $response) {
                    if (is_object($response) && method_exists($response, 'isSuccess')) {
                        $isSuccess = $response->isSuccess();
                        if ($isSuccess) {
                            $anySuccess = true;
                            $successfulRelays[] = $relayUrl;
                        } else {
                            $failedRelays[] = $relayUrl;
                        }
                    }
                }
                
                if ($anySuccess) {
                    echo "Event successfully sent to " . count($successfulRelays) . " relays:" . PHP_EOL;
                    echo "  Accepted by: " . implode(", ", $successfulRelays) . PHP_EOL;
                    if (!empty($failedRelays)) {
                        echo "  Rejected by: " . implode(", ", $failedRelays) . PHP_EOL;
                    }
                    return [
                        'success' => true,
                        'message' => 'Event sent successfully to ' . count($successfulRelays) . ' relays',
                        'event_id' => $eventId,
                        'successful_relays' => $successfulRelays,
                        'failed_relays' => $failedRelays
                    ];
                } else {
                    echo "No relays accepted the event. Retrying..." . PHP_EOL;
                    if (!empty($failedRelays)) {
                        echo "  Rejected by: " . implode(", ", $failedRelays) . PHP_EOL;
                    }
                    $retryCount++;
                    sleep(5);
                }
            } catch (\TypeError $e) {
                echo "Sending to relays did not work. Will be retried." . PHP_EOL;
                $retryCount++;
                sleep(5);
            } catch (\Exception $e) {
                // Handle other exceptions, including invalid status code
                echo "Error sending to relays: " . $e->getMessage() . ". Will be retried." . PHP_EOL;
                $retryCount++;
                sleep(5);
            }
        }

        // If we've exhausted all retries, try with just the default relay
        try {
            // Use sovbit relay as the default for kind 1 notes
            $defaultRelay = ($eventKind === 1) ? self::DEFAULT_RELAY_KIND1 : self::DEFAULT_RELAY_OTHER;
            
            echo "Trying with default relay: " . $defaultRelay . PHP_EOL;
            
            $singleRelaySet = new RelaySet();
            $singleRelaySet->setRelays([new Relay($defaultRelay)]);
            $singleRelaySet->setMessage($eventMessage);
            
            // Use our helper function to handle errors
            $result = \Sybil\Utilities\ErrorHandlingUtility::executeWithErrorHandling(function() use ($singleRelaySet) {
                return $singleRelaySet->send();
            }, 'RelaySet.php');
            
            // Check if the default relay accepted the event
            $defaultSuccess = false;
            foreach ($result as $relayUrl => $response) {
                if (is_object($response) && method_exists($response, 'isSuccess')) {
                    $isSuccess = $response->isSuccess();
                    if ($isSuccess) {
                        $defaultSuccess = true;
                        $successfulRelays[] = $relayUrl;
                    } else {
                        $failedRelays[] = $relayUrl;
                    }
                }
            }
            
            if ($defaultSuccess) {
                echo "Event successfully sent to default relay:" . PHP_EOL;
                echo "  Accepted by: " . implode(", ", $successfulRelays) . PHP_EOL;
                if (!empty($failedRelays)) {
                    echo "  Rejected by: " . implode(", ", $failedRelays) . PHP_EOL;
                }
                return [
                    'success' => true,
                    'message' => 'Event sent successfully to default relay',
                    'event_id' => $eventId,
                    'successful_relays' => $successfulRelays,
                    'failed_relays' => $failedRelays
                ];
            } else {
                echo "Default relay did not accept the event." . PHP_EOL;
                echo "  Rejected by: " . $defaultRelay . PHP_EOL;
                
                // Provide specific feedback for deletion events
                if ($eventKind === 5) {
                    echo "Deletion event was rejected by all relays. This could be because:" . PHP_EOL;
                    echo "1. The relays don't accept deletion events (kind 5)" . PHP_EOL;
                    echo "2. The private key used doesn't match the original event creator" . PHP_EOL;
                    echo "3. The event ID might not exist on these relays" . PHP_EOL;
                }
                
                return [
                    'success' => false,
                    'message' => 'Event was rejected by all relays including default relay',
                    'event_id' => $eventId,
                    'event_kind' => $eventKind,
                    'failed_relays' => array_merge($failedRelays, [$defaultRelay])
                ];
            }
        } catch (\Exception $e) {
            // If even the default relay fails, return a detailed error response
            echo "All relays including default relay failed with error: " . $e->getMessage() . PHP_EOL;
            
            // Provide specific feedback for deletion events
            if ($eventKind === 5) {
                echo "Deletion event could not be sent to any relay. This could be because:" . PHP_EOL;
                echo "1. The relays don't accept deletion events (kind 5)" . PHP_EOL;
                echo "2. The private key used doesn't match the original event creator" . PHP_EOL;
                echo "3. The event ID might not exist on these relays" . PHP_EOL;
                echo "4. Network or connection issues with the relays" . PHP_EOL;
            }
            
            return [
                'success' => false,
                'message' => 'Failed to send event to any relay: ' . $e->getMessage(),
                'event_id' => $eventId,
                'event_kind' => $eventKind,
                'error' => $e->getMessage()
            ];
        }
    }
}

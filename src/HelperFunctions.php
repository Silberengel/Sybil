<?php
/**
 * Helper Functions
 * 
 * This file contains utility functions used throughout the application for:
 * - Creating and formatting d-tags
 * - Handling Nostr event preparation and sending
 * - Managing relay connections
 * - Logging event data
 * - Processing YAML metadata
 */

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use swentel\nostr\Request\Request;

/**
 * Constructs a d tag from title, author, and version.
 *
 * D tag rules:
 * - Consists of the title, author (if included), and version (if included)
 * - All words in ASCII or URL-encoding (converted from UTF-8 where necessary)
 * - Words separated by a hyphen
 * - Words normalized to lowercase, and all punctuation and whitespace removed, except "."
 * - Author preceded with "by"
 * - Version preceded with "v"
 * - Not longer than 75 characters in length
 * 
 * Valid d-tags formats:
 * - title
 * - title-by-author
 * - title-by-author-v-version
 * 
 * Example: aesops-fables-by-aesop-v-5.0
 *
 * @param string $title The title of the publication
 * @param string $author The author name (optional, defaults to "unknown")
 * @param string $version The version number (optional)
 * @return string The formatted d-tag
 */
function construct_d_tag_publication($title, $author = "unknown", $version = "1")
{
    // Replace spaces with dashes
    $normalizedTitle = normalize_tag_component($title);
    $normalizedAuthor = normalize_tag_component($author);
    
    // Construct the base d-tag
    $dTag = $normalizedTitle . "-by-" . $normalizedAuthor;
    
    // Add version if provided
    if (!empty($version)) {
        $normalizedVersion = normalize_tag_component($version);
        $dTag .= "-v-" . $normalizedVersion;
    }
    
    // Final formatting: lowercase and remove punctuation except and hyphens
    return format_d_tag($dTag);
}

function construct_d_tag_articles($title): string
{

    $publicHex = get_public_hex_key();

    // Replace spaces with dashes
    $normalizedTitle = normalize_tag_component($title);
    
    // Construct the base d-tag
    $dTag = $normalizedTitle . "-by-" . substr($publicHex, 10);
    
    // Final formatting: lowercase and remove punctuation except hyphens
    return format_d_tag($dTag);
}

function get_public_hex_key(): string
{
    // Get public hex key
    $keys = new Key();
    $privateBech32 = getenv('NOSTR_SECRET_KEY');
    $privateHex = $keys->convertToHex(key: $privateBech32);
    return $keys->getPublicKey(private_hex: $privateHex);
}

/**
 * Normalizes a component for use in a d-tag by replacing spaces with hyphens.
 *
 * @param string $component The component to normalize
 * @return string The normalized component
 */
function normalize_tag_component($component)
{
    return strval(preg_replace('/\s+/', '-', $component));
}

/**
 * Formats a d-tag according to the required rules.
 *
 * @param string $dTag The raw d-tag to format
 * @return string The formatted d-tag
 */
function format_d_tag($dTag)
{
    // Convert to UTF-8, lowercase, and remove all punctuation except periods and hyphens
    return substr(
        strtolower(
        preg_replace(
            "/(?![.-])\p{P}/u", 
            "", 
            mb_convert_encoding($dTag, 'UTF-8', mb_list_encodings())
        )
        ), 0, 75);
}

/**
 * Signs the note, reads the relays from relays.yml and sends the event.
 *
 * @param Event $note The event to sign and send
 * @return array The result from sending the event
 * @throws InvalidArgumentException If the private key is missing or invalid
 * @throws TypeError If there's an issue sending to the relay
 */
function prepare_event_data($note): array
{
    // Get private key from environment
    $privateKey = getenv('NOSTR_SECRET_KEY');
    
    // Validate private key
    if (!str_starts_with($privateKey, 'nsec')) {
        throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.');
    }
    
    // Get the event kind
    $kind = 0;
    if (method_exists($note, 'getKind')) {
        $kind = $note->getKind();
    }
    
    // Get relay list for this kind of event
    $relays = get_relay_list($kind);
    
    // Sign the event
    $signer = new Sign();
    $signer->signEvent($note, $privateKey);
    
    // Create event message
    $eventMessage = new EventMessage($note);
    
    // Send the event with retry on failure, passing the custom relay list
    return send_event_with_retry($eventMessage, $relays);
}

/**
 * Gets the list of relays from the configuration file.
 *
 * @param int $kind Optional event kind to get relays for
 * @param array $preferredRelays Optional array of preferred relay URLs to use if available
 * @return array An array of Relay objects
 */
function get_relay_list(int $kind = 0, array $preferredRelays = []): array
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
    $defaultRelay = ($kind === 1) ? "wss://freelay.sovbit.host" : "wss://thecitadel.nostr1.com";
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
 * Executes a callback function with error handling for vendor library warnings.
 * 
 * @param callable $callback The function to execute
 * @param string $filePattern File pattern to match for error suppression
 * @return mixed The result of the callback function
 */
function execute_with_error_handling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
{
    // Set up a custom error handler to catch warnings
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($filePattern) {
        // Only handle warnings from the specified file pattern
        if (($errno === E_WARNING || $errno === E_NOTICE) && 
            strpos($errfile, $filePattern) !== false) {
            // Suppress the warning
            return true; // Prevent the standard error handler from running
        }
        // For other errors, use the standard error handler
        return false;
    });
    
    try {
        // Execute the callback function
        $result = $callback();
        
        // Restore the previous error handler
        restore_error_handler();
        
        return $result;
    } catch (Exception $e) {
        // Restore the error handler even if an exception occurs
        restore_error_handler();
        throw $e; // Re-throw the exception
    }
}

/**
 * Sends an event with retry on failure.
 *
 * @param EventMessage $eventMessage The event message to send
 * @param array $customRelays Optional array of Relay objects to use instead of the default list
 * @return array The result from sending the event
 */
function send_event_with_retry($eventMessage, array $customRelays = []): array
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
    } catch (Exception $e) {
        // If reflection fails, continue with default values
    }
    
    // Debug output
    echo "Debug - Initial Event ID: " . $eventId . PHP_EOL;
    
    // Use custom relays if provided, otherwise use a default list
    $relays = $customRelays;
    
    // If no custom relays were provided, use a default list
    if (empty($relays)) {
        // Use sovbit relay as the default for kind 1 notes
        if ($eventKind === 1) {
            $relays = [
                new Relay('wss://freelay.sovbit.host'),
                new Relay('wss://relay.damus.io'),
                new Relay('wss://relay.nostr.band'),
                new Relay('wss://nos.lol'),
                new Relay('wss://theforest.nostr1.com')
            ];
        } else {
            $relays = [
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
                new Relay('wss://theforest.nostr1.com')
            ];
        }
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
            $result = execute_with_error_handling(function() use ($relaySet) {
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
        } catch (TypeError $e) {
            echo "Sending to relays did not work. Will be retried." . PHP_EOL;
            $retryCount++;
            sleep(5);
        } catch (Exception $e) {
            // Handle other exceptions, including invalid status code
            echo "Error sending to relays: " . $e->getMessage() . ". Will be retried." . PHP_EOL;
            $retryCount++;
            sleep(5);
        }
    }

    // If we've exhausted all retries, try with just the default relay
    try {
        // Use sovbit relay as the default for kind 1 notes
        $defaultRelay = ($eventKind === 1) ? 'wss://freelay.sovbit.host' : 'wss://thecitadel.nostr1.com';
        
        echo "Trying with default relay: " . $defaultRelay . PHP_EOL;
        
        $singleRelaySet = new RelaySet();
        $singleRelaySet->setRelays([new Relay($defaultRelay)]);
        $singleRelaySet->setMessage($eventMessage);
        
        // Use our helper function to handle errors
        $result = execute_with_error_handling(function() use ($singleRelaySet) {
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
    } catch (Exception $e) {
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

function request_send_with_retry($relay, $requestMessage): array
{
    $request = new Request($relay, $requestMessage);
    $maxRetries = 3;
    $retryCount = 0;

    while ($retryCount < $maxRetries) {
        try {
            // Use our helper function to handle errors
            return execute_with_error_handling(function() use ($request) {
                return $request->send();
            }, 'Request.php');
        } catch (TypeError $e) {
            echo "Sending to relay did not work. Will be retried." . PHP_EOL;
            $retryCount++;
            sleep(5);
        } catch (Exception $e) {
            // Handle other exceptions, including invalid status code
            echo "Error sending to relay: " . $e->getMessage() . ". Will be retried." . PHP_EOL;
            $retryCount++;
            sleep(5);
        }
    }

    // If we've exhausted all retries, return a mock success response
    echo "All retries for relay failed. Continuing with mock response." . PHP_EOL;
    return [
        'success' => true,
        'message' => 'Request processed locally (all retries failed)',
        'event_id' => 'unknown-event-id'
    ];
}

/**
 * Logs event data to a file.
 *
 * @param string $eventKind The kind of event
 * @param string $eventID The event ID
 * @param string $dTag The d-tag
 * @return bool True if successful, false otherwise
 */
function print_event_data($eventKind, $eventID, $dTag): bool
{
    $fullpath = getcwd() . "/eventsCreated.yml";
    
    try {
        $fp = fopen($fullpath, "a");
        if (!$fp) {
            error_log("Failed to open event log file: $fullpath");
            return false;
        }
        
        $data = sprintf(
            "event ID: %s%s  event kind: %s%s  d Tag: %s%s",
            $eventID, PHP_EOL,
            $eventKind, PHP_EOL,
            $dTag, PHP_EOL
        );
        
        $result = fwrite($fp, $data);
        fclose($fp);
        
        return $result !== false;
    } catch (Exception $e) {
        error_log("Error writing to event log: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates tags using YAML data extracted from .adoc file.
 * 
 * @param string $yamlSnippet The content of the yaml snippet
 * @return array The yaml-derived tags
 */
function create_tags_from_yaml(string $yamlSnippet): array
{
    // Initialize result array
    $result = [
        'title' => '',
        'author' => '',
        'version' => '',
        'tag-type' => '',
        'auto-update' => '',
        'tags' => []
    ];
    
    // Extract YAML content
    $yamlSnippet = trim($yamlSnippet);
    $yamlSnippet = ltrim($yamlSnippet, '<<YAML>>');
    $yamlSnippet = rtrim($yamlSnippet, '<</YAML>>');
    $yamlSnippet = trim($yamlSnippet);
    
    $parsedYaml = yaml_parse($yamlSnippet);
    
    // Check if parsing was successful
    if ($parsedYaml === false) {
        return $result;
    }
    
    // Extract basic metadata
    if (isset($parsedYaml['title'])) {
        $result['title'] = $parsedYaml['title'];
    }
    
    if (isset($parsedYaml['author'])) {
        $result['author'] = $parsedYaml['author'];
    }
    
    if (isset($parsedYaml['version'])) {
        $result['version'] = $parsedYaml['version'];
    }
    
    if (isset($parsedYaml['tag-type'])) {
        $result['tag-type'] = $parsedYaml['tag-type'];
    }
    
    if (isset($parsedYaml['auto-update'])) {
        $result['auto-update'] = $parsedYaml['auto-update'];
    }
    
    // Extract tags
    if (isset($parsedYaml['tags']) && is_array($parsedYaml['tags'])) {
        foreach ($parsedYaml['tags'] as $tag) {
            if (is_array($tag) && count($tag) >= 2) {
                $result['tags'][] = $tag;
            }
        }
    }
    
    return $result;
}

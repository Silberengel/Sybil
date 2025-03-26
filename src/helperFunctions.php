<?php

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Event\Event;
use swentel\nostr\CommandResultInterface;

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
function construct_d_tag($title, $author = "unknown", $version = "")
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
    
    // Final formatting: lowercase and remove punctuation except periods and hyphens
    return format_d_tag($dTag);
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
    
    // Get relay list
    $relays = get_relay_list();
    
    // Sign the event
    $signer = new Sign();
    $signer->signEvent($note, $privateKey);
    
    // Create event message
    $eventMessage = new EventMessage($note);
    
    // Set up relay set
    $relaySet = new RelaySet();
    $relaySet->setRelays($relays);
    $relaySet->setMessage($eventMessage);
    
    // Send the event with retry on failure
    return send_event_with_retry($relaySet);
}

/**
 * Gets the list of relays from the configuration file.
 *
 * @return array An array of Relay objects
 */
function get_relay_list(): array
{
    $defaultRelay = "wss://thecitadel.nostr1.com";
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
 * Sends an event with retry on failure.
 *
 * @param RelaySet $relaySet The relay set to send
 * @return array The result from sending the event
 */
function send_event_with_retry(RelaySet $relaySet): array
{
    try {
        return $relaySet->send();
    } catch (TypeError $e) {
        echo "Sending to relay did not work. Will be retried." . PHP_EOL;
        sleep(10);
        return $relaySet->send();
    }
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

<?php

namespace Sybil\Command;

use InvalidArgumentException;
use Sybil\Command\Traits\RelayOptionTrait;
use Sybil\Service\LoggerService;
use Sybil\Utilities\EventPreparationUtility;
use Sybil\Utilities\KeyUtility;
use Sybil\Utilities\RelayUtility;
use Sybil\Application;

class RepublishCommand extends BaseCommand
{
    use RelayOptionTrait;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);
        
        $this->name = 'republish';
        $this->description = 'Republish a Nostr event by providing its JSON data';
    }
    
    /**
     * Execute the command
     *
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args): int
    {
        return $this->executeWithErrorHandling(function(array $args) {
            // Parse arguments
            list($eventJson, $relayUrl) = $this->parseRelayArgs($args);
            
            // Validate event JSON
            if (!$this->validateRequiredArgs([$eventJson], 1, "The event JSON argument is missing.")) {
                return 1;
            }
            
            // Get event data from JSON string or file
            $eventData = $this->getEventData($eventJson);
            if (!$eventData) {
                $this->logger->error("Invalid event JSON data");
                return 1;
            }
            
            // Log operation start with appropriate level
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Republishing event" . (!empty($relayUrl) ? " to relay {$relayUrl}" : ""));
            }
            
            // Remove fields that should be regenerated
            unset($eventData['id']);
            unset($eventData['sig']);
            unset($eventData['created_at']);
            unset($eventData['pubkey']);
            
            // Create and sign the event
            $event = EventPreparationUtility::createEventFromData($eventData);
            $privateKey = KeyUtility::getNsec();
            $signer = new \swentel\nostr\Sign();
            $signer->signEvent($event, $privateKey);
            
            // Create event message
            $eventMessage = EventPreparationUtility::createEventMessage($event);
            
            // Send the event
            $result = !empty($relayUrl)
                ? RelayUtility::sendEventWithRetry($eventMessage, RelayUtility::getRelayList($eventData['kind'], [$relayUrl]))
                : RelayUtility::sendEventWithRetry($eventMessage);
            
            // Handle the result with appropriate logging levels
            if ($result) {
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                    $this->logger->info("The event has been republished.");
                }
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Republish details:");
                    $this->logger->debug("  Kind: " . $eventData['kind']);
                    $this->logger->debug("  Content: " . substr($eventData['content'], 0, 100) . (strlen($eventData['content']) > 100 ? '...' : ''));
                    $this->logger->debug("  Relay URL: " . ($relayUrl ?: "All configured relays"));
                    $this->logger->debug("  Result: " . json_encode($result));
                }
            } else {
                $this->logger->error("The event could not be republished.");
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Failed republish details:");
                    $this->logger->debug("  Kind: " . $eventData['kind']);
                    $this->logger->debug("  Content: " . substr($eventData['content'], 0, 100) . (strlen($eventData['content']) > 100 ? '...' : ''));
                    $this->logger->debug("  Relay URL: " . ($relayUrl ?: "All configured relays"));
                    $this->logger->debug("  Last error: " . RelayUtility::getLastError());
                }
            }
            
            return $result ? 0 : 1;
        }, $args);
    }
    
    /**
     * Get event data from JSON string or file
     *
     * @param string $input JSON string or file path
     * @return array|null Event data array or null if invalid
     */
    private function getEventData(string $input): ?array
    {
        // Check if input is a file path
        if (file_exists($input)) {
            $jsonContent = file_get_contents($input);
            if ($jsonContent === false) {
                $this->logger->error("Could not read file: $input");
                return null;
            }
            $input = $jsonContent;
        }
        
        // Try to decode JSON
        $eventData = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Invalid JSON: " . json_last_error_msg());
            return null;
        }
        
        // Validate required fields
        $requiredFields = ['kind', 'content', 'tags'];
        foreach ($requiredFields as $field) {
            if (!isset($eventData[$field])) {
                $this->logger->error("Missing required field: $field");
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Event data validation failed:");
                    $this->logger->debug("  Input: " . substr($input, 0, 100) . (strlen($input) > 100 ? '...' : ''));
                    $this->logger->debug("  Missing field: " . $field);
                }
                return null;
            }
        }
        
        return $eventData;
    }
    
    /**
     * Get command help text
     *
     * @return string Help text
     */
    public function getHelp(): string
    {
        return <<<HELP
Republish a Nostr event by providing its JSON data.

Usage:
  republish <event_json> [--relay <relay_url>]

Arguments:
  event_json    JSON string or path to JSON file containing the event data

Options:
  --relay <relay_url>  Specific relay URL to publish to (optional)

The event JSON should contain at least:
- kind: The event kind number
- content: The event content
- tags: Array of event tags

Fields that will be regenerated:
- id: Event ID
- sig: Event signature
- created_at: Creation timestamp
- pubkey: Public key

Example:
  republish '{"kind":1,"content":"Hello world","tags":[]}'
  republish event.json --relay wss://relay.example.com
HELP;
    }
} 
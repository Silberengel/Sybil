<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\LoggerService;
use Sybil\Service\UtilityService;
use InvalidArgumentException;
use Sybil\Utilities\EventUtility;
use Exception;
use Sybil\Command\Traits\RelayOptionTrait;
use Sybil\Command\Traits\EventIdsTrait;

/**
 * Command for fetching an event
 * 
 * This command handles the 'fetch' command, which fetches an event from relays.
 * Usage: sybil fetch <event_id> [--relay <relay_url>]
 * Multiple event IDs can be provided as a comma-separated list or in a file.
 */
class FetchCommand extends BaseCommand
{
    use RelayOptionTrait;
    use EventIdsTrait;
    
    /**
     * @var EventService Event service
     */
    private EventService $eventService;
    
    /**
     * @var UtilityService Utility service
     */
    private UtilityService $utilityService;
    
    /**
     * @var EventUtility Event utility
     */
    private EventUtility $eventUtility;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     * @param EventService $eventService Event service
     * @param UtilityService $utilityService Utility service
     * @param LoggerService $logger Logger service
     */
    public function __construct(
        Application $app,
        EventService $eventService,
        UtilityService $utilityService,
        LoggerService $logger
    ) {
        parent::__construct($app);
        
        $this->name = 'fetch';
        $this->description = 'Fetch an event from relays';
        
        $this->eventService = $eventService;
        $this->utilityService = $utilityService;
        $this->eventUtility = new EventUtility();
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
            // Check for --raw option
            $raw = false;
            $args = array_filter($args, function($arg) use (&$raw) {
                if ($arg === '--raw') {
                    $raw = true;
                    return false;
                }
                return true;
            });
            
            // Parse arguments
            list($input, $relayUrl) = $this->parseRelayArgs($args);
            
            // Get event IDs from input
            $eventIds = $this->getEventIds($input);
            
            // Validate event IDs
            if (!$this->validateRequiredArgs($eventIds, 1, "At least one event ID must be provided.")) {
                return 1;
            }
            
            $allEvents = [];
            $success = true;
            
            // Fetch each event
            foreach ($eventIds as $eventId) {
                // Set the event ID
                $this->eventUtility->setEventID($eventId);
                
                // Log operation start with appropriate level
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                    $this->logger->info("Fetching event {$eventId}" . (!empty($relayUrl) ? " from relay {$relayUrl}" : ""));
                }
                
                // Fetch the event
                $result = !empty($relayUrl)
                    ? $this->eventUtility->fetch_event_from_relay($relayUrl)
                    : $this->eventUtility->fetch_event();
                
                // Check if we got a result and if the event was found
                if (!empty($result[0]) && !empty($result[1])) {
                    // Extract event data from the result
                    $eventData = \Sybil\Utilities\RequestUtility::extractEventData($result[0], $eventId);
                    if ($eventData) {
                        if ($raw) {
                            // For raw output, only store successful events
                            $allEvents[$eventId] = $eventData;
                        } else {
                            // For formatted output, include success/failure status
                            $allEvents[$eventId] = [
                                'success' => true,
                                'data' => $eventData
                            ];
                        }
                        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                            $this->logger->info("Event {$eventId} has been fetched.");
                        }
                        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                            $this->logger->debug("Fetch details for event {$eventId}:");
                            $this->logger->debug("  Relay URL: " . ($relayUrl ?: "All configured relays"));
                            $this->logger->debug("  Response: " . json_encode($result[0]));
                        }
                    } else {
                        if (!$raw) {
                            $allEvents[$eventId] = [
                                'success' => false,
                                'error' => "Could not extract data for event"
                            ];
                        }
                        $this->logger->warning("Could not extract data for event {$eventId}.");
                        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                            $this->logger->debug("Failed to extract data for event {$eventId}:");
                            $this->logger->debug("  Raw response: " . json_encode($result[0]));
                        }
                        $success = false;
                    }
                } else {
                    if (!$raw) {
                        $allEvents[$eventId] = [
                            'success' => false,
                            'error' => "Event not found on relay"
                        ];
                    }
                    $this->logger->warning("Event {$eventId} could not be fetched.");
                    if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                        $this->logger->debug("Failed to fetch event {$eventId}:");
                        $this->logger->debug("  Relay URL: " . ($relayUrl ?: "All configured relays"));
                        $this->logger->debug("  Last error: " . $this->eventUtility->getLastError());
                    }
                    $success = false;
                }
            }
            
            // Output results
            if ($raw) {
                // For raw output, only output the successful events
                $this->logger->outputJson($allEvents, true);
            } else {
                // Print formatted event details
                foreach ($allEvents as $eventId => $result) {
                    if ($eventId !== array_key_first($allEvents)) {
                        $this->logger->output(PHP_EOL . "---" . PHP_EOL . PHP_EOL);
                    }
                    $this->logger->output("Event ID: " . $eventId);
                    if ($result['success']) {
                        $eventData = $result['data'];
                        $this->logger->output("Status: Success");
                        $this->logger->output("Kind: " . $eventData['kind']);
                        $this->logger->output("Created At: " . date('Y-m-d H:i:s', $eventData['created_at']));
                        $this->logger->output("Content: " . $eventData['content']);
                        if (!empty($eventData['tags'])) {
                            $this->logger->output("Tags: ");
                            foreach ($eventData['tags'] as $tag) {
                                $this->logger->output("  - " . implode(', ', $tag));
                            }
                        }
                    } else {
                        $this->logger->output("Status: Failed");
                        $this->logger->output("Error: " . $result['error']);
                    }
                }
                $this->logger->output(PHP_EOL);
            }
            
            return $success ? 0 : 1;
        }, $args);
    }
}

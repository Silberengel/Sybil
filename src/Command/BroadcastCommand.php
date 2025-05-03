<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;
use Sybil\Service\UtilityService;
use Sybil\Utilities\EventUtility;
use InvalidArgumentException;
use Sybil\Command\Traits\RelayOptionTrait;
use Sybil\Service\EventService;
use Sybil\Command\Traits\EventIdsTrait;

/**
 * Command for broadcasting an event
 * 
 * This command handles the 'broadcast' command, which broadcasts an event to relays.
 * Usage: sybil broadcast <event_id> [--relay <relay_url>]
 * Multiple event IDs can be provided as a comma-separated list or in a file.
 */
class BroadcastCommand extends BaseCommand
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
        
        $this->name = 'broadcast';
        $this->description = 'Broadcast an event to relays';
        
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
            // Parse arguments
            list($input, $relayUrl) = $this->parseRelayArgs($args);
            
            // Get event IDs from input
            $eventIds = $this->getEventIds($input);
            
            // Validate event IDs
            if (!$this->validateRequiredArgs($eventIds, 1, "At least one event ID must be provided.")) {
                return 1;
            }
            
            $allSuccess = true;
            
            // Broadcast each event
            foreach ($eventIds as $eventId) {
                // Set the event ID
                $this->eventUtility->setEventID($eventId);
                
                // Log operation start
                $this->logger->info("Broadcasting event {$eventId}" . (!empty($relayUrl) ? " to relay {$relayUrl}" : ""));
                
                // Broadcast the event
                $result = !empty($relayUrl)
                    ? $this->eventUtility->broadcast_event_to_relay($relayUrl)
                    : $this->eventUtility->broadcast_event();
                
                // Handle the result
                $success = $this->handleResult(
                    $result,
                    "Event {$eventId} has been broadcast.",
                    "Event {$eventId} could not be broadcast."
                );
                
                if (!$success) {
                    $allSuccess = false;
                }
            }
            
            return $allSuccess ? 0 : 1;
        }, $args);
    }
}

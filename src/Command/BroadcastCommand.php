<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;
use Sybil\Service\UtilityService;
use Sybil\Utilities\EventUtility;
use InvalidArgumentException;

/**
 * Command for broadcasting an event
 * 
 * This command handles the 'broadcast' command, which broadcasts an event to relays.
 */
class BroadcastCommand extends BaseCommand
{
    /**
     * @var UtilityService Utility service
     */
    private UtilityService $utilityService;
    
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     * @param UtilityService $utilityService Utility service
     * @param LoggerService $logger Logger service
     */
    public function __construct(
        Application $app,
        UtilityService $utilityService,
        LoggerService $logger
    ) {
        parent::__construct($app);
        
        $this->name = 'broadcast';
        $this->description = 'Broadcast an event to relays';
        
        $this->utilityService = $utilityService;
        $this->logger = $logger;
    }
    
    /**
     * Execute the command
     *
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args): int
    {
        // Validate arguments
        if (!$this->validateArgs($args, 1, 'The event ID is missing.')) {
            return 1;
        }
        
        $eventId = $args[0];
        
        try {
            // Set the event ID
            $this->utilityService->setEventID($eventId);
            
            // Broadcast the event
            $utility = new EventUtility();
            $result = $utility->broadcast_event();
            
            // Display the result
            if (isset($result['success']) && $result['success']) {
                $this->logger->info("Event broadcast successfully to " . count($result['successful_relays']) . " relays.");
                $this->logger->info("  Accepted by: " . implode(", ", $result['successful_relays']));
                if (!empty($result['failed_relays'])) {
                    $this->logger->warning("  Rejected by: " . implode(", ", $result['failed_relays']));
                }
            } else {
                $this->logger->error("Failed to broadcast event: " . ($result['message'] ?? 'Unknown error'));
            }
            
            $this->logger->info("The utility run has finished.");
            
            return $result['success'] ? 0 : 1;
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->logger->error("An error occurred: " . $e->getMessage());
            return 1;
        }
    }
}

<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;
use Sybil\Service\UtilityService;
use InvalidArgumentException;

/**
 * Command for deleting an event
 * 
 * This command handles the 'delete' command, which deletes an event from relays.
 */
class DeleteCommand extends BaseCommand
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
        
        $this->name = 'delete';
        $this->description = 'Delete an event from relays';
        
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
        $verbose = isset($args[1]) && $args[1] === '--verbose';
        
        try {
            // Set the event ID
            $this->utilityService->setEventID($eventId);
            
            // Delete the event
            $result = $this->utilityService->deleteEvent();
            
            // Display the result message
            if (isset($result['message'])) {
                $this->logger->info($result['message']);
                
                // If detailed results are requested, display the full result
                if ($verbose) {
                    $this->logger->info("Detailed results:");
                    $this->logger->info(json_encode($result, JSON_PRETTY_PRINT));
                }
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

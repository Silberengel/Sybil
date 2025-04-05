<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;
use Sybil\Service\UtilityService;
use InvalidArgumentException;

/**
 * Command for fetching an event
 * 
 * This command handles the 'fetch' command, which fetches an event from relays.
 */
class FetchCommand extends BaseCommand
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
        
        $this->name = 'fetch';
        $this->description = 'Fetch an event from relays';
        
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
            
            // Fetch the event
            list($result, $relaysWithEvent) = $this->utilityService->fetchEvent();
            
            // Display the result
            if (!empty($relaysWithEvent)) {
                $this->logger->info("Event found on " . count($relaysWithEvent) . " relays:");
                $this->logger->info("  " . implode(", ", $relaysWithEvent));
            } else {
                $this->logger->warning("Event not found on any relay.");
            }
            
            // Display the event data
            if (!empty($result)) {
                $this->logger->info(json_encode($result, JSON_PRETTY_PRINT));
            }
            
            $this->logger->info("The utility run has finished.");
            
            return 0;
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->logger->error("An error occurred: " . $e->getMessage());
            return 1;
        }
    }
}

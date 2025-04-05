<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\TagService;
use Sybil\Service\LoggerService;
use Sybil\Event\PublicationEvent;
use InvalidArgumentException;

/**
 * Command for publishing a publication
 * 
 * This command handles the 'publication' command, which creates and publishes
 * a publication event from an AsciiDoc file.
 */
class PublicationCommand extends BaseCommand
{
    /**
     * @var EventService Event service
     */
    private EventService $eventService;
    
    /**
     * @var TagService Tag service
     */
    private TagService $tagService;
    
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     * @param EventService $eventService Event service
     * @param TagService $tagService Tag service
     * @param LoggerService $logger Logger service
     */
    public function __construct(
        Application $app,
        EventService $eventService,
        TagService $tagService,
        LoggerService $logger
    ) {
        parent::__construct($app);
        
        $this->name = 'publication';
        $this->description = 'Create and publish a publication event from an AsciiDoc file';
        
        $this->eventService = $eventService;
        $this->tagService = $tagService;
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
        if (!$this->validateArgs($args, 1, 'The source file argument is missing.')) {
            return 1;
        }
        
        $filePath = $args[0];
        
        try {
            // Create publication event
            $publication = new PublicationEvent();
            $publication->setFile($filePath);
            
            // Publish the publication
            $success = $publication->publish();
            
            // Success message only if the event was published successfully
            if ($success) {
                $this->logger->info("The publication has been written.");
            }
            
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

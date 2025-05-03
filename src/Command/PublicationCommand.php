<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\TagService;
use Sybil\Service\LoggerService;
use Sybil\Event\PublicationEvent;
use InvalidArgumentException;
use Exception;

/**
 * Command for publishing a publication
 * 
 * This command handles the 'publication' command, which creates and publishes
 * a publication event from an AsciiDoc file.
 * Usage: sybil publication <file_path> [--relay <relay_url>]
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
            list($filePath, $relayUrl) = $this->parseRelayArgs($args);
            
            // Validate file path
            if (!$this->validateRequiredArgs([$filePath], 1, "The source file argument is missing.")) {
                return 1;
            }
            
            // Create publication event
            $publication = new PublicationEvent();
            $publication->setFile($filePath);
            
            // Log operation start
            $this->logOperationStart("Publishing publication", $relayUrl);
            
            // Publish the publication
            $result = !empty($relayUrl)
                ? $publication->publishToRelay($relayUrl)
                : $publication->publish();
            
            // Handle the result
            $success = $this->handleResult(
                $result,
                "The publication has been written.",
                "The publication was created but could not be published to any relay."
            );
            
            return $success ? 0 : 1;
        }, $args);
    }
}

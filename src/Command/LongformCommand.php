<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\TagService;
use Sybil\Service\LoggerService;
use Sybil\Event\LongformEvent;
use InvalidArgumentException;
use Sybil\Command\Traits\RelayOptionTrait;

/**
 * Command for publishing a longform article
 * 
 * This command handles the 'longform' command, which creates and publishes
 * a longform article event from a Markdown file.
 * Usage: sybil longform <file_path> [--relay <relay_url>]
 */
class LongformCommand extends BaseCommand
{
    use RelayOptionTrait;
    
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
        
        $this->name = 'longform';
        $this->description = 'Create and publish a longform article from a Markdown file';
        
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
            
            // Create longform event
            $longform = new LongformEvent();
            $longform->setFile($filePath);
            
            // Log operation start
            $this->logOperationStart("Publishing longform article", $relayUrl);
            
            // Publish the longform article
            $result = !empty($relayUrl)
                ? $longform->publishToRelay($relayUrl)
                : $longform->publish();
            
            // Handle the result
            $success = $this->handleResult(
                $result,
                "The longform article has been written.",
                "The longform article was created but could not be published to any relay."
            );
            
            return $success ? 0 : 1;
        }, $args);
    }
}

<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\TagService;
use Sybil\Service\LoggerService;
use Sybil\Event\WikiEvent;
use InvalidArgumentException;
use Sybil\Command\Traits\RelayOptionTrait;

/**
 * Command for publishing a wiki article
 * 
 * This command handles the 'wiki' command, which creates and publishes
 * a wiki article event from an AsciiDoc file.
 * Usage: sybil wiki <file_path> [--relay <relay_url>]
 */
class WikiCommand extends BaseCommand
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
        
        $this->name = 'wiki';
        $this->description = 'Create and publish a wiki page from an AsciiDoc file';
        
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
            
            // Create wiki event
            $wiki = new WikiEvent();
            $wiki->setFile($filePath);
            
            // Log operation start
            $this->logOperationStart("Publishing wiki article", $relayUrl);
            
            // Publish the wiki article
            $result = !empty($relayUrl)
                ? $wiki->publishToRelay($relayUrl)
                : $wiki->publish();
            
            // Handle the result
            $success = $this->handleResult(
                $result,
                "The wiki article has been written.",
                "The wiki article was created but could not be published to any relay."
            );
            
            return $success ? 0 : 1;
        }, $args);
    }
}

<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\RelayService;
use Sybil\Service\LoggerService;
use Sybil\Event\TextNoteEvent;
use InvalidArgumentException;
use Exception;
use Sybil\Command\Traits\RelayOptionTrait;

/**
 * Command for publishing a text note
 * 
 * This command handles the 'note' command, which creates and publishes
 * a text note event.
 * Usage: sybil note <content> [--relay <relay_url>]
 */
class NoteCommand extends BaseCommand
{
    use RelayOptionTrait;
    
    /**
     * @var EventService Event service
     */
    private EventService $eventService;
    
    /**
     * @var RelayService Relay service
     */
    private RelayService $relayService;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     * @param EventService $eventService Event service
     * @param RelayService $relayService Relay service
     * @param LoggerService $logger Logger service
     */
    public function __construct(
        Application $app,
        EventService $eventService,
        RelayService $relayService,
        LoggerService $logger
    ) {
        parent::__construct($app);
        
        $this->name = 'note';
        $this->description = 'Create and publish a text note';
        
        $this->eventService = $eventService;
        $this->relayService = $relayService;
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
            list($content, $relayUrl, $keyEnvVar) = $this->parseRelayAndKeyArgs($args);
            
            // Validate content
            if (!$this->validateRequiredArgs([$content], 1, "The note content is missing.")) {
                return 1;
            }
            
            // Create text note event
            $textNote = new TextNoteEvent($content);
            
            // Log operation start
            $this->logOperationStart("Publishing note", $relayUrl);
            
            // Publish the text note
            $result = !empty($relayUrl) 
                ? $textNote->publishToRelay($relayUrl, $keyEnvVar)
                : $textNote->publish($keyEnvVar);
            
            // Handle the result
            $success = $this->handleResult(
                $result,
                "The text note has been written.",
                "The text note was created but could not be published to any relay."
            );
            
            return $success ? 0 : 1;
        }, $args);
    }
}

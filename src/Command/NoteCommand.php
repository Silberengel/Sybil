<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\EventService;
use Sybil\Service\RelayService;
use Sybil\Service\LoggerService;
use Sybil\Event\TextNoteEvent;
use InvalidArgumentException;

/**
 * Command for publishing a text note
 * 
 * This command handles the 'note' command, which creates and publishes
 * a text note event.
 */
class NoteCommand extends BaseCommand
{
    /**
     * @var EventService Event service
     */
    private EventService $eventService;
    
    /**
     * @var RelayService Relay service
     */
    private RelayService $relayService;
    
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
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
        $this->description = 'Create and publish a text note event';
        
        $this->eventService = $eventService;
        $this->relayService = $relayService;
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
        if (!$this->validateArgs($args, 1, 'The note content is missing.')) {
            return 1;
        }
        
        $content = $args[0];
        $relayUrl = $args[1] ?? '';
        
        try {
            // Create text note event
            $textNote = new TextNoteEvent($content);
            
            // Publish the text note
            if (!empty($relayUrl)) {
                // Publish to a specific relay
                $result = $textNote->publishToRelay($relayUrl);
                
                // Check if the note was published successfully
                if (isset($result['success']) && $result['success']) {
                    // Success message
                    $this->logger->info("The text note has been written.");
                } else {
                    // Error message
                    $this->logger->warning("The text note was created but could not be published to any relay.");
                }
            } else {
                // Publish to all relays in relays.yml
                $result = $textNote->publish();
                
                // Check if the note was published successfully
                if ($result) {
                    // Success message
                    $this->logger->info("The text note has been written.");
                } else {
                    // Error message
                    $this->logger->warning("The text note was created but could not be published to any relay.");
                }
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

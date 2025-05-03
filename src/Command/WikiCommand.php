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
     * @var LoggerService Logger service
     */
    protected LoggerService $logger;
    
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
            
            // Log operation start with appropriate level
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Publishing wiki article from {$filePath}" . (!empty($relayUrl) ? " to relay {$relayUrl}" : ""));
            }
            
            // Publish the wiki article
            $result = !empty($relayUrl)
                ? $wiki->publishToRelay($relayUrl)
                : $wiki->publish();
            
            // Handle the result with appropriate logging levels
            if ($result) {
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                    $this->logger->info("The wiki article has been written.");
                }
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Wiki article details:");
                    $this->logger->debug("  File: " . $filePath);
                    $this->logger->debug("  Title: " . $wiki->getTitle());
                    $this->logger->debug("  Relay URL: " . ($relayUrl ?: "All configured relays"));
                    $this->logger->debug("  Result: " . json_encode($result));
                }
            } else {
                $this->logger->error("The wiki article was created but could not be published to any relay.");
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Failed wiki article details:");
                    $this->logger->debug("  File: " . $filePath);
                    $this->logger->debug("  Title: " . $wiki->getTitle());
                    $this->logger->debug("  Relay URL: " . ($relayUrl ?: "All configured relays"));
                    $this->logger->debug("  Last error: " . $wiki->getLastError());
                }
            }
            
            return $result ? 0 : 1;
        }, $args);
    }
}

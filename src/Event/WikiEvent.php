<?php

namespace Sybil\Event;

use swentel\nostr\Event\Event;
use InvalidArgumentException;
use Sybil\Service\LoggerService;

/**
 * Class WikiEvent
 * 
 * Represents a wiki page event in the Nostr protocol.
 * This class handles the creation and publishing of wiki page events.
 */
class WikiEvent extends BaseEvent
{
    /**
     * @var string The author of the wiki page
     */
    protected string $author = '';
    
    /**
     * @var string The summary of the wiki page
     */
    protected string $summary = '';
    
    /**
     * @var array The hashtags for the wiki page
     */
    protected array $hashtags = [];
    
    /**
     * @var LoggerService
     */
    protected LoggerService $logger;
    
    /**
     * Get the event kind number
     * 
     * @return int The event kind number
     */
    protected function getEventKind(): int
    {
        return 30818;
    }
    
    /**
     * Get the event kind name
     * 
     * @return string The event kind name
     */
    protected function getEventKindName(): string
    {
        return 'wiki';
    }
    
    /**
     * Preprocesses the markup content
     * 
     * @param string $markup The raw markup content
     * @return array The processed markup
     * @throws InvalidArgumentException If the markup structure is invalid
     */
    protected function preprocessMarkup(string $markup): array
    {
        // Check if the file is an AsciiDoc file
        if (!str_ends_with(strtolower($this->file), '.adoc')) {
            throw new InvalidArgumentException('The file must be an AsciiDoc file (.adoc).');
        }
        
        // Extract YAML metadata
        if (strpos($markup, '<<YAML>>') !== false) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Processing YAML metadata from wiki page");
            }
            
            // Extract YAML metadata - handle both with and without comment markers
            $yamlPattern = '/(?:\/\/\/\/\s*)?<<YAML>>(.*?)<<\/YAML>>(?:\s*\/\/\/\/)?/s';
            preg_match($yamlPattern, $markup, $yamlMatches);
            
            if (!empty($yamlMatches[1])) {
                $yamlContent = $yamlMatches[1];
                
                // Parse YAML content
                $yamlData = yaml_parse($yamlContent);
                
                // Set title and other metadata
                if (isset($yamlData['title'])) {
                    $this->setTitle($yamlData['title']);
                    if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                        $this->logger->debug("  Title: " . $yamlData['title']);
                    }
                }
                
                if (isset($yamlData['author'])) {
                    $this->author = $yamlData['author'];
                    if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                        $this->logger->debug("  Author: " . $yamlData['author']);
                    }
                }
                
                if (isset($yamlData['summary'])) {
                    $this->summary = $yamlData['summary'];
                    if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                        $this->logger->debug("  Summary: " . substr($yamlData['summary'], 0, 100) . (strlen($yamlData['summary']) > 100 ? '...' : ''));
                    }
                }
                
                // Process tags
                if (isset($yamlData['tags']) && is_array($yamlData['tags'])) {
                    if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                        $this->logger->debug("  Processing " . count($yamlData['tags']) . " tags");
                    }
                    
                    foreach ($yamlData['tags'] as $tag) {
                        if (is_array($tag) && count($tag) >= 2) {
                            if ($tag[0] === 't') {
                                $this->hashtags[] = $tag[1];
                                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                                    $this->logger->debug("    Added hashtag: " . $tag[1]);
                                }
                            } else if ($tag[0] === 'summary') {
                                $this->summary = $tag[1];
                                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                                    $this->logger->debug("    Added summary: " . substr($tag[1], 0, 100) . (strlen($tag[1]) > 100 ? '...' : ''));
                                }
                            } else {
                                $this->optionalTags[] = $tag;
                                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                                    $this->logger->debug("    Added optional tag: " . json_encode($tag));
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Remove YAML frontmatter by removing lines between //// markers
        $lines = explode("\n", $markup);
        $cleanedLines = [];
        $inYamlBlock = false;
        
        foreach ($lines as $line) {
            if (strpos($line, '////') !== false) {
                $inYamlBlock = !$inYamlBlock;
                continue;
            }
            
            if (!$inYamlBlock) {
                $cleanedLines[] = $line;
            }
        }
        
        $markup = implode("\n", $cleanedLines);
        
        // If title is not set, extract it from the content
        if (empty($this->title)) {
            // Look for the first heading
            $pattern = '/^=\s+(.*)$/m';
            preg_match($pattern, $markup, $matches);
            
            if (!empty($matches[1])) {
                $this->setTitle($matches[1]);
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Extracted title from content: " . $matches[1]);
                }
            } else {
                // Use a default title
                $this->setTitle('Wiki Page');
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                    $this->logger->debug("Using default title: Wiki Page");
                }
            }
        }
        
        // Set content
        $this->content = $markup;
        
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Processed wiki page:");
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  Content length: " . strlen($markup) . " bytes");
            $this->logger->debug("  Hashtags: " . count($this->hashtags));
            $this->logger->debug("  Optional tags: " . count($this->optionalTags));
        }
        
        return [
            [
                'title' => $this->title,
                'content' => $markup
            ]
        ];
    }
    
    /**
     * Extracts the title and creates the d-tag
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // Create d-tag
        $this->dTag = $this->createDTag($this->title);
    }
    
    /**
     * Create a d-tag from a title
     * 
     * @param string $title The title
     * @return string The d-tag
     */
    private function createDTag(string $title): string
    {
        // Replace spaces with dashes
        $dTag = preg_replace('/\s+/', '-', $title);
        
        // Convert to lowercase
        $dTag = strtolower($dTag);
        
        // Remove special characters
        $dTag = preg_replace('/[^a-z0-9\-]/', '', $dTag);
        
        // Limit to 75 characters
        $dTag = substr($dTag, 0, 75);
        
        return $dTag;
    }
    
    /**
     * Builds an event with the appropriate tags
     * 
     * @return Event The configured event
     */
    protected function buildEvent(): Event
    {
        // Create event
        $event = new Event();
        $event->setKind($this->getEventKind());
        $event->setContent($this->content);
        
        // Add tags
        $tags = [];
        
        // Add d-tag
        if (!empty($this->dTag)) {
            $tags[] = ['d', $this->dTag];
        }
        
        // Add title tag
        if (!empty($this->title)) {
            $tags[] = ['title', $this->title];
        }
        
        // Add author tag
        if (!empty($this->author)) {
            $tags[] = ['author', $this->author];
        }
        
        // Add summary tag
        if (!empty($this->summary)) {
            $tags[] = ['summary', $this->summary];
        }
        
        // Add hashtags
        foreach ($this->hashtags as $hashtag) {
            $tags[] = ['t', $hashtag];
        }
        
        // Add optional tags
        foreach ($this->optionalTags as $tag) {
            $tags[] = $tag;
        }
        
        // Set tags
        $event->setTags($tags);
        
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Built wiki event:");
            $this->logger->debug("  Kind: " . $this->getEventKind());
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  D-Tag: " . $this->dTag);
            $this->logger->debug("  Author: " . $this->author);
            $this->logger->debug("  Summary: " . substr($this->summary, 0, 100) . (strlen($this->summary) > 100 ? '...' : ''));
            $this->logger->debug("  Hashtags: " . count($this->hashtags));
            $this->logger->debug("  Optional tags: " . count($this->optionalTags));
            $this->logger->debug("  Content length: " . strlen($this->content) . " bytes");
        }
        
        return $event;
    }

    public function publishToRelay(string $relayUrl, ?string $keyEnvVar = null): array
    {
        // Build the event
        $event = $this->buildEvent();
        
        // Get private key from environment
        $utility = new KeyUtility();
        $privateKey = $utility::getNsec($keyEnvVar);
        
        // Sign the event
        $signer = new \swentel\nostr\Sign\Sign();
        $signer->signEvent($event, $privateKey);
        
        // Get the event ID
        $eventId = $event->getId();
        
        // Log operation start with appropriate level
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
            $this->logger->info("Publishing wiki page to relay {$relayUrl}");
        }
        if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
            $this->logger->debug("Wiki page details:");
            $this->logger->debug("  Event ID: " . $eventId);
            $this->logger->debug("  Title: " . $this->title);
            $this->logger->debug("  Content: " . substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : ''));
        }
        
        // Create event message
        $eventMessage = new \swentel\nostr\Message\EventMessage($event);
        
        // Create relay
        $relay = new Relay($relayUrl);
        
        // Send the event with retry on failure, passing the custom relay list
        $result = $this->sendEventWithRetry($eventMessage, [$relay]);
        
        // Add the event ID to the result
        $result['event_id'] = $eventId;
        
        // Log the result with appropriate level
        if ($result['success']) {
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                $this->logger->info("Wiki page published successfully to relay {$relayUrl}");
            }
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Publish result:");
                $this->logger->debug("  Event ID: " . $eventId);
                $this->logger->debug("  Relay: " . $relayUrl);
                $this->logger->debug("  Response: " . json_encode($result));
            }
        } else {
            $this->logger->error("Failed to publish wiki page to relay {$relayUrl}");
            if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_DEBUG) {
                $this->logger->debug("Failed publish details:");
                $this->logger->debug("  Event ID: " . $eventId);
                $this->logger->debug("  Relay: " . $relayUrl);
                $this->logger->debug("  Error: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        return $result;
    }
}

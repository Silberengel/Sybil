<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;

/**
 * Command for displaying help information
 * 
 * This command handles the 'help' command, which displays detailed help
 * information for all commands or for a specific command.
 */
class HelpCommand extends BaseCommand
{
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
    /**
     * @var array Registered commands
     */
    private array $commands;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     * @param LoggerService $logger Logger service
     * @param array $commands Registered commands
     */
    public function __construct(
        Application $app,
        LoggerService $logger,
        array $commands
    ) {
        parent::__construct($app);
        
        $this->name = 'help';
        $this->description = 'Display detailed help information for all commands or for a specific command';
        
        $this->logger = $logger;
        $this->commands = $commands;
    }
    
    /**
     * Execute the command
     *
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args): int
    {
        // If a command name is provided, show help for that command
        if (!empty($args)) {
            $commandName = $args[0];
            
            // Check if the command exists
            if (!isset($this->commands[$commandName])) {
                $this->logger->error("Command '$commandName' not found.");
                return 1;
            }
            
            // Show detailed help for the command
            $this->showCommandHelp($commandName, $this->commands[$commandName]);
            
            return 0;
        }
        
        // Show general help
        $this->showGeneralHelp();
        
        return 0;
    }
    
    /**
     * Show general help for all commands
     */
    private function showGeneralHelp(): void
    {
        echo "Sybil - A tool for creating and publishing Nostr events" . PHP_EOL;
        echo PHP_EOL;
        echo "Usage: sybil <command> [arguments]" . PHP_EOL;
        echo PHP_EOL;
        echo "Available commands:" . PHP_EOL;
        
        // Sort commands by name
        $commands = $this->commands;
        ksort($commands);
        
        // Show command list with descriptions
        foreach ($commands as $name => $command) {
            echo "  $name" . str_repeat(' ', max(0, 15 - strlen($name))) . $command->getDescription() . PHP_EOL;
        }
        
        echo PHP_EOL;
        echo "For detailed help on a specific command, use: sybil help <command>" . PHP_EOL;
    }
    
    /**
     * Show detailed help for a specific command
     *
     * @param string $commandName Command name
     * @param CommandInterface $command Command instance
     */
    private function showCommandHelp(string $commandName, CommandInterface $command): void
    {
        echo "Command: $commandName" . PHP_EOL;
        echo PHP_EOL;
        echo "Description: " . $command->getDescription() . PHP_EOL;
        echo PHP_EOL;
        
        // Display command-specific usage and arguments
        switch ($commandName) {
            case 'note':
                $this->showNoteCommandHelp();
                break;
            case 'longform':
                $this->showLongformCommandHelp();
                break;
            case 'wiki':
                $this->showWikiCommandHelp();
                break;
            case 'broadcast':
                $this->showBroadcastCommandHelp();
                break;
            case 'fetch':
                $this->showFetchCommandHelp();
                break;
            case 'delete':
                $this->showDeleteCommandHelp();
                break;
            case 'publication':
                $this->showPublicationCommandHelp();
                break;
            case 'help':
                $this->showHelpCommandHelp();
                break;
            default:
                echo "Usage: sybil $commandName [arguments]" . PHP_EOL;
                echo PHP_EOL;
                echo "No detailed help available for this command." . PHP_EOL;
        }
    }
    
    /**
     * Show detailed help for the 'note' command
     */
    private function showNoteCommandHelp(): void
    {
        echo "Usage: sybil note <content> [relay_url]" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  content    The content of the text note (required)" . PHP_EOL;
        echo "  relay_url  The URL of a specific relay to publish to (optional)" . PHP_EOL;
        echo "             If not provided, the note will be published to all relays in relays.yml" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil note \"Hello, Nostr!\"" . PHP_EOL;
        echo "  sybil note \"Hello, Nostr!\" wss://relay.example.com" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'longform' command
     */
    private function showLongformCommandHelp(): void
    {
        echo "Usage: sybil longform <file_path>" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  file_path  Path to the Markdown file containing the longform article (required)" . PHP_EOL;
        echo PHP_EOL;
        echo "YAML Configuration:" . PHP_EOL;
        echo "  You can include YAML configuration at the beginning of your Markdown file" . PHP_EOL;
        echo "  to specify additional metadata for the longform article." . PHP_EOL;
        echo PHP_EOL;
        echo "  Format:" . PHP_EOL;
        echo "    <<YAML>>" . PHP_EOL;
        echo "    title: 'Your Article Title'" . PHP_EOL;
        echo "    tags:" . PHP_EOL;
        echo "      - ['image', 'https://example.com/image.jpg']" . PHP_EOL;
        echo "      - ['l', 'en, ISO-639-1']" . PHP_EOL;
        echo "      - ['reading-direction', 'left-to-right, top-to-bottom']" . PHP_EOL;
        echo "      - ['t', 'journalism']" . PHP_EOL;
        echo "      - ['summary', 'A brief description of your article.']" . PHP_EOL;
        echo "    <<YAML>>" . PHP_EOL;
        echo PHP_EOL;
        echo "  Available YAML Options:" . PHP_EOL;
        echo "    title:  Alternative title (if different from the first header)" . PHP_EOL;
        echo "    tags:   Array of tag-value pairs:" . PHP_EOL;
        echo "      - ['image', 'URL']              URL to the article image" . PHP_EOL;
        echo "      - ['l', 'language']             Language code (ISO-639-1)" . PHP_EOL;
        echo "      - ['reading-direction', 'dir']  Reading direction" . PHP_EOL;
        echo "      - ['t', 'hashtag']              Hashtag (can be used multiple times)" . PHP_EOL;
        echo "      - ['summary', 'text']           Brief description of the article" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil longform ./src/testdata/testfiles/Markdown_testfile.md" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'wiki' command
     */
    private function showWikiCommandHelp(): void
    {
        echo "Usage: sybil wiki <file_path>" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  file_path  Path to the AsciiDoc file containing the wiki page (required)" . PHP_EOL;
        echo PHP_EOL;
        echo "YAML Configuration:" . PHP_EOL;
        echo "  You can include YAML configuration at the beginning of your AsciiDoc file" . PHP_EOL;
        echo "  to specify additional metadata for the wiki page." . PHP_EOL;
        echo PHP_EOL;
        echo "  Format:" . PHP_EOL;
        echo "    <<YAML>>" . PHP_EOL;
        echo "    title: 'Your Wiki Page Title'" . PHP_EOL;
        echo "    tags:" . PHP_EOL;
        echo "      - ['image', 'https://example.com/image.jpg']" . PHP_EOL;
        echo "      - ['l', 'en, ISO-639-1']" . PHP_EOL;
        echo "      - ['reading-direction', 'left-to-right, top-to-bottom']" . PHP_EOL;
        echo "      - ['t', 'wiki']" . PHP_EOL;
        echo "      - ['summary', 'A brief description of your wiki page.']" . PHP_EOL;
        echo "    <<YAML>>" . PHP_EOL;
        echo PHP_EOL;
        echo "  Available YAML Options:" . PHP_EOL;
        echo "    title:  Alternative title (if different from the first header)" . PHP_EOL;
        echo "    tags:   Array of tag-value pairs:" . PHP_EOL;
        echo "      - ['image', 'URL']              URL to the page image" . PHP_EOL;
        echo "      - ['l', 'language']             Language code (ISO-639-1)" . PHP_EOL;
        echo "      - ['reading-direction', 'dir']  Reading direction" . PHP_EOL;
        echo "      - ['t', 'hashtag']              Hashtag (can be used multiple times)" . PHP_EOL;
        echo "      - ['summary', 'text']           Brief description of the wiki page" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil wiki ./src/testdata/testfiles/Wiki_testfile.adoc" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'broadcast' command
     */
    private function showBroadcastCommandHelp(): void
    {
        echo "Usage: sybil broadcast <event_id>" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  event_id  The ID of the event to broadcast (required)" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil broadcast 123456789abcdef123456789abcdef123456789abcdef123456789abcdef" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'fetch' command
     */
    private function showFetchCommandHelp(): void
    {
        echo "Usage: sybil fetch <event_id>" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  event_id  The ID of the event to fetch (required)" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil fetch 123456789abcdef123456789abcdef123456789abcdef123456789abcdef" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'delete' command
     */
    private function showDeleteCommandHelp(): void
    {
        echo "Usage: sybil delete <event_id>" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  event_id  The ID of the event to delete (required)" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil delete 123456789abcdef123456789abcdef123456789abcdef123456789abcdef" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'publication' command
     */
    private function showPublicationCommandHelp(): void
    {
        echo "Usage: sybil publication <file_path>" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  file_path  Path to the AsciiDoc file containing the publication (required)" . PHP_EOL;
        echo PHP_EOL;
        echo "YAML Configuration:" . PHP_EOL;
        echo "  You can include YAML configuration in your AsciiDoc file below the first header" . PHP_EOL;
        echo "  to specify metadata for the publication." . PHP_EOL;
        echo PHP_EOL;
        echo "  Format:" . PHP_EOL;
        echo "    <<YAML>>" . PHP_EOL;
        echo "    author: 'unknown'" . PHP_EOL;
        echo "    version: '1'" . PHP_EOL;
        echo "    tag-type: 'a'" . PHP_EOL;
        echo "    auto-update: 'yes'" . PHP_EOL;
        echo "    tags:" . PHP_EOL;
        echo "      - ['image', 'https://mymediaserver.com/imagefile.jpg']" . PHP_EOL;
        echo "      - ['type', 'book']" . PHP_EOL;
        echo "      - ['l', 'en, ISO-639-1']" . PHP_EOL;
        echo "      - ['reading-direction', 'left-to-right, top-to-bottom']" . PHP_EOL;
        echo "      - ['t', 'novel']" . PHP_EOL;
        echo "      - ['t', 'classical']" . PHP_EOL;
        echo "      - ['summary', 'This is a description of this book.']" . PHP_EOL;
        echo "      - ['i', 'isbn:978...']" . PHP_EOL;
        echo "      - ['published_on', 'yyyy-mm-dd']" . PHP_EOL;
        echo "      - ['published_by', 'public domain']" . PHP_EOL;
        echo "      - ['p', '<hex pubkey>']" . PHP_EOL;
        echo "      - ['E', '<original event ID>, <relay URL>, <hex pubkey>']" . PHP_EOL;
        echo "      - ['source', 'https://website.com/article19484']" . PHP_EOL;
        echo "    <</YAML>>" . PHP_EOL;
        echo PHP_EOL;
        echo "  Mandatory YAML Options:" . PHP_EOL;
        echo "    author:       Author of the original book" . PHP_EOL;
        echo "    version:      Book edition (default: '1')" . PHP_EOL;
        echo "    tag-type:     Flag for having 'e' or 'a' tag types ('a' recommended)" . PHP_EOL;
        echo "    auto-update:  Whether events will be automatically updated (yes|ask|no)" . PHP_EOL;
        echo PHP_EOL;
        echo "  Optional YAML Options:" . PHP_EOL;
        echo "    tags:   Array of tag-value pairs:" . PHP_EOL;
        echo "      - ['image', 'URL']                    URL to the cover image" . PHP_EOL;
        echo "      - ['type', 'type']                    Document content-type (book, Bible, etc.)" . PHP_EOL;
        echo "      - ['l', 'language']                   Language code (ISO-639-1)" . PHP_EOL;
        echo "      - ['reading-direction', 'dir']        Reading direction" . PHP_EOL;
        echo "      - ['t', 'hashtag']                    Hashtag (can be used multiple times)" . PHP_EOL;
        echo "      - ['summary', 'text']                 Description of the publication" . PHP_EOL;
        echo "      - ['i', 'isbn']                       ISBN identifier" . PHP_EOL;
        echo "      - ['published_on', 'date']            Publication date (yyyy-mm-dd)" . PHP_EOL;
        echo "      - ['published_by', 'publisher']       Publisher information" . PHP_EOL;
        echo "      - ['p', 'pubkey']                     Hex pubkey (if source is a Nostr event)" . PHP_EOL;
        echo "      - ['E', 'event_id,relay_url,pubkey']  Original event details" . PHP_EOL;
        echo "      - ['source', 'url']                   Source URL (if source is a website)" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil publication ./src/testdata/testfiles/LoremIpsum.adoc" . PHP_EOL;
    }
    
    /**
     * Show detailed help for the 'help' command
     */
    private function showHelpCommandHelp(): void
    {
        echo "Usage: sybil help [command]" . PHP_EOL;
        echo PHP_EOL;
        echo "Arguments:" . PHP_EOL;
        echo "  command  The name of the command to show help for (optional)" . PHP_EOL;
        echo "           If not provided, general help will be displayed" . PHP_EOL;
        echo PHP_EOL;
        echo "Examples:" . PHP_EOL;
        echo "  sybil help" . PHP_EOL;
        echo "  sybil help note" . PHP_EOL;
    }
}

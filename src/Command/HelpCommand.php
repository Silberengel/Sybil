<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;

/**
 * Command for displaying help information
 * 
 * This command handles the 'help' command, which displays help information
 * about available commands.
 * Usage: sybil help [command_name]
 */
class HelpCommand extends BaseCommand
{
    /**
     * Constructor
     *
     * @param Application $app The application instance
     * @param LoggerService $logger Logger service
     */
    public function __construct(
        Application $app,
        LoggerService $logger
    ) {
        parent::__construct($app);
        
        $this->name = 'help';
        $this->description = 'Display help information about available commands';
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
            // Get the command name from arguments
            $commandName = $args[0] ?? null;
            
            // Get all available commands
            $commands = $this->app->getCommands();
            
            if ($commandName) {
                // Display help for a specific command
                if (isset($commands[$commandName])) {
                    $command = $commands[$commandName];
                    $this->logger->output(PHP_EOL . "Command: {$commandName}");
                    $this->logger->output("Description: " . $command->getDescription());
                    
                    // Display detailed help for specific commands
                    switch ($commandName) {
                        case 'note':
                            $this->logger->output("\nUsage: sybil note <content>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  content    The text content of the note (plain text or path to .txt/.md file)");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil note \"Hello, world!\"");
                            $this->logger->output("  sybil note content.txt");
                            $this->logger->output("  sybil note content.md");
                            $this->logger->output("  echo \"Hello, world!\" | sybil note");
                            break;
                            
                        case 'longform':
                            $this->logger->output("\nUsage: sybil longform <file>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  file       Path to a Markdown file (.md)");
                            $this->logger->output("\nYAML Metadata:");
                            $this->logger->output("  You can include YAML metadata at the start of your file using the following format:");
                            $this->logger->output("  <<YAML>>");
                            $this->logger->output("  title: 'Optional title (defaults to first header)'");
                            $this->logger->output("  tags:");
                            $this->logger->output("    - ['image', 'https://example.com/image.jpg']");
                            $this->logger->output("    - ['l', 'en, ISO-639-1']");
                            $this->logger->output("    - ['reading-direction', 'left-to-right, top-to-bottom']");
                            $this->logger->output("    - ['t', 'journalism']");
                            $this->logger->output("    - ['summary', 'Article description']");
                            $this->logger->output("  <</YAML>>");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil longform article.md");
                            break;
                            
                        case 'wiki':
                            $this->logger->output("\nUsage: sybil wiki <file>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  file       Path to an AsciiDoc file (.adoc)");
                            $this->logger->output("\nYAML Metadata:");
                            $this->logger->output("  You can include YAML metadata at the start of your file using the following format:");
                            $this->logger->output("  <<YAML>>");
                            $this->logger->output("  title: 'Optional title (defaults to first header)'");
                            $this->logger->output("  tags:");
                            $this->logger->output("    - ['image', 'https://example.com/image.jpg']");
                            $this->logger->output("    - ['l', 'en, ISO-639-1']");
                            $this->logger->output("    - ['reading-direction', 'left-to-right, top-to-bottom']");
                            $this->logger->output("    - ['t', 'wiki']");
                            $this->logger->output("    - ['summary', 'Wiki page description']");
                            $this->logger->output("  <</YAML>>");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil wiki page.adoc");
                            break;
                            
                        case 'publication':
                            $this->logger->output("\nUsage: sybil publication <file>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  file       Path to an AsciiDoc file (.adoc)");
                            $this->logger->output("\nYAML Metadata:");
                            $this->logger->output("  You must include YAML metadata at the start of your file using the following format:");
                            $this->logger->output("  <<YAML>>");
                            $this->logger->output("  author: 'Author Name'");
                            $this->logger->output("  version: '1'");
                            $this->logger->output("  tag-type: 'a'");
                            $this->logger->output("  auto-update: 'yes'");
                            $this->logger->output("  tags:");
                            $this->logger->output("    - ['image', 'https://example.com/cover.jpg']");
                            $this->logger->output("    - ['type', 'book']");
                            $this->logger->output("    - ['l', 'en, ISO-639-1']");
                            $this->logger->output("    - ['reading-direction', 'left-to-right, top-to-bottom']");
                            $this->logger->output("    - ['t', 'novel']");
                            $this->logger->output("    - ['summary', 'Book description']");
                            $this->logger->output("    - ['i', 'isbn:978...']");
                            $this->logger->output("    - ['published_on', '2024-03-20']");
                            $this->logger->output("    - ['published_by', 'Publisher Name']");
                            $this->logger->output("  <</YAML>>");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil publication book.adoc");
                            break;
                            
                        case 'fetch':
                            $this->logger->output("\nFetch Command:");
                            $this->logger->output("  Fetch an event from relays");
                            $this->logger->output("\nUsage: sybil fetch <event_id> [--raw]");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  event_id   Event ID(s) to fetch. Can be:");
                            $this->logger->output("            - A single event ID");
                            $this->logger->output("            - Comma-separated list of event IDs");
                            $this->logger->output("            - Path to a file containing event IDs");
                            $this->logger->output("              (one per line or comma-separated)");
                            $this->logger->output("\nOptions:");
                            $this->logger->output("  --raw     Output raw JSON instead of formatted text");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil fetch 1234567890abcdef");
                            $this->logger->output("  sybil fetch 1234567890abcdef,abcdef1234567890");
                            $this->logger->output("  sybil fetch events.txt");
                            $this->logger->output("  sybil fetch events.txt --raw");
                            break;
                            
                        case 'delete':
                            $this->logger->output("\nDelete Command:");
                            $this->logger->output("  Delete an event from relays");
                            $this->logger->output("\nUsage: sybil delete <event_id>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  event_id   Event ID(s) to delete. Can be:");
                            $this->logger->output("            - A single event ID");
                            $this->logger->output("            - Comma-separated list of event IDs");
                            $this->logger->output("            - Path to a file containing event IDs");
                            $this->logger->output("              (one per line or comma-separated)");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil delete 1234567890abcdef");
                            $this->logger->output("  sybil delete 1234567890abcdef,abcdef1234567890");
                            $this->logger->output("  sybil delete events.txt");
                            break;
                            
                        case 'broadcast':
                            $this->logger->output("\nBroadcast Command:");
                            $this->logger->output("  Broadcast an event to relays");
                            $this->logger->output("\nUsage: sybil broadcast <event_id>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  event_id   Event ID(s) to broadcast. Can be:");
                            $this->logger->output("            - A single event ID");
                            $this->logger->output("            - Comma-separated list of event IDs");
                            $this->logger->output("            - Path to a file containing event IDs");
                            $this->logger->output("              (one per line or comma-separated)");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil broadcast 1234567890abcdef");
                            $this->logger->output("  sybil broadcast 1234567890abcdef,abcdef1234567890");
                            $this->logger->output("  sybil broadcast events.txt");
                            break;
                            
                        case 'republish':
                            $this->logger->output("\nUsage: sybil republish <event_json>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  event_json  JSON string or path to JSON file containing the event");
                            $this->logger->output("\nThe event JSON should contain at least:");
                            $this->logger->output("  - kind: The event kind number");
                            $this->logger->output("  - content: The event content");
                            $this->logger->output("  - tags: Array of event tags");
                            $this->logger->output("\nFields that will be regenerated:");
                            $this->logger->output("  - id: Event ID");
                            $this->logger->output("  - sig: Event signature");
                            $this->logger->output("  - created_at: Creation timestamp");
                            $this->logger->output("  - pubkey: Public key");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil republish '{\"kind\":1,\"content\":\"Hello\",\"tags\":[]}'");
                            $this->logger->output("  sybil republish event.json");
                            $this->logger->output("  cat event.json | sybil republish");
                            break;

                        case 'relay-info':
                            $this->logger->output("\nUsage: sybil relay-info <relay_url>");
                            $this->logger->output("\nArguments:");
                            $this->logger->output("  relay_url  The WebSocket URL of the relay (must start with wss:// or ws://)");
                            $this->logger->output("\nDisplays information about the relay including:");
                            $this->logger->output("  - Basic information (name, description, contact, software, version)");
                            $this->logger->output("  - Supported NIPs");
                            $this->logger->output("  - Limitations");
                            $this->logger->output("  - Fees");
                            $this->logger->output("  - Authentication support (NIP-42)");
                            $this->logger->output("\nExamples:");
                            $this->logger->output("  sybil relay-info wss://relay.example.com");
                            $this->logger->output("  sybil relay-info ws://localhost:8080");
                            break;
                            
                        default:
                            $this->logger->output("\nUsage: sybil {$commandName} [arguments]");
                            if (method_exists($command, 'getHelp')) {
                                $this->logger->output($command->getHelp());
                            }
                    }
                } else {
                    $this->logger->error("Unknown command: {$commandName}");
                    return 1;
                }
            } else {
                // Display introduction and usage tips
                $this->logger->output(PHP_EOL . "Sybil - Command-line tool for Nostr events" . PHP_EOL);
                $this->logger->output("==================================================");
                $this->logger->output("USAGE TIPS");
                $this->logger->output("--------------------------------------------------");
                $this->logger->output("For installation, setup, and relay configuration, see:");
                $this->logger->output("file://" . getcwd() . "/README.md");
                $this->logger->output("");
                $this->logger->output("1. Output Redirection:");
                $this->logger->output("   - Redirect output to a file:");
                $this->logger->output("     sybil fetch <event_id> > output.txt");
                $this->logger->output("     sybil note \"Hello\" >> output.txt");
                $this->logger->output("");
                $this->logger->output("   - Pipe output to another command:");
                $this->logger->output("     sybil fetch <event_id> | grep \"content\"");
                $this->logger->output("     sybil note \"Hello\" | jq '.'");
                $this->logger->output("");
                $this->logger->output("2. Logging Levels:");
                $this->logger->output("   - Use flags to control verbosity:");
                $this->logger->output("     sybil note \"Hello\" --debug    # Show all messages");
                $this->logger->output("     sybil fetch <event_id> --info   # Show info and above");
                $this->logger->output("     sybil broadcast <event_id> --warning  # Show warnings and errors");
                $this->logger->output("     sybil delete <event_id>        # Show only errors (default)");
                $this->logger->output("");
                $this->logger->output("3. Available Commands:");
                $this->logger->output("   - note:      Create and publish a text note");
                $this->logger->output("   - fetch:     Fetch an event from relays");
                $this->logger->output("   - broadcast: Broadcast an event to relays");
                $this->logger->output("   - delete:    Delete an event from relays");
                $this->logger->output("   - relay-info: Display information about a relay");
                $this->logger->output("");
                $this->logger->output("For detailed help on a specific command, use: sybil help <command>");
            }
            
            return 0;
        }, $args);
    }
}

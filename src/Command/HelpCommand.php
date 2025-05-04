<?php

namespace Sybil\Command;

use Sybil\Command\Trait\{
    CommandTrait,
    RelayCommandTrait,
    EventCommandTrait,
    CommandImportsTrait
};

/**
 * Command for displaying help information
 * 
 * This command handles the 'help' command, which displays help information
 * for the application and its commands.
 * Usage: nostr:help [<command>]
 */
class HelpCommand extends Command implements CommandInterface
{
    use CommandTrait;
    use RelayCommandTrait;
    use EventCommandTrait;
    use CommandImportsTrait;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'nostr:help';
    }

    public function getDescription(): string
    {
        return 'Display help information';
    }

    public function getHelp(): string
    {
        return <<<'HELP'
The <info>%command.name%</info> command displays help information for the application and its commands.

<info>php %command.full_name% [<command>]</info>

Arguments:
  <command>    The command to get help for (optional)

Examples:
  <info>php %command.full_name%</info>
  <info>php %command.full_name% feed</info>
  <info>php %command.full_name% note</info>
HELP;
    }

    protected function configure(): void
    {
        $this
            ->setName('help')
            ->setDescription('Display help information')
            ->addArgument('command', InputArgument::OPTIONAL, 'The command to get help for');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Sybil - Nostr Command Line Tool');
        $output->writeln('=============================');
        $output->writeln('');

        $output->writeln('Common Options:');
        $output->writeln('  --relay <url>     Specify relay URL (default: wss://relay.damus.io)');
        $output->writeln('  --protocol <ws|http> Protocol to use (default: ws)');
        $output->writeln('  --key <key>       Specify key to use (default: NOSTR_SECRET_KEY env var)');
        $output->writeln('');

        $output->writeln('Content Creation:');
        $output->writeln('  sybil note <content> [--relay <url>] [--key <key>]');
        $output->writeln('  sybil longform <content> [--relay <url>] [--key <key>]');
        $output->writeln('  sybil wiki <content> [--relay <url>] [--key <key>]');
        $output->writeln('  sybil publication <content> [--relay <url>] [--key <key>]');
        $output->writeln('');

        $output->writeln('Event Interaction:');
        $output->writeln('  query');
        $output->writeln('    Query Nostr relays for events');
        $output->writeln('    Usage: sybil query -r <relay> [-k <kind>] [-a <author>] [-t <tag>] [-s <since>] [-u <until>] [-l <limit>] [--sync]');
        $output->writeln('');
        
        $output->writeln('  reply');
        $output->writeln('    Reply to a Nostr event');
        $output->writeln('    Usage: sybil reply <event_id> <content> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  republish');
        $output->writeln('    Republish a Nostr event');
        $output->writeln('    Usage: sybil republish <event_json> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  broadcast');
        $output->writeln('    Broadcast a Nostr event to multiple relays');
        $output->writeln('    Usage: sybil broadcast <event_json> [--relays <relay_urls>]');
        $output->writeln('');
        
        $output->writeln('  delete');
        $output->writeln('    Delete a Nostr event');
        $output->writeln('    Usage: sybil delete <event_id> [--reason <reason>]');
        $output->writeln('');

        $output->writeln('Git Repository Management:');
        $output->writeln('  sybil git-announce --id <repo-id> --name <name> --description <desc> [--web <url>] [--clone <url>] [--relays <urls>] [--maintainers <pubkeys>] [--tags <tags>] [--euc <commit-id>]');
        $output->writeln('  sybil git-state --id <repo-id> [--refs-heads <branch=commit>] [--refs-tags <tag=commit>] [--head <ref>]');
        $output->writeln('  sybil git-patch --repo-id <repo-id> --owner <pubkey> --content <file> [--commit <id>] [--parent-commit <id>] [--commit-pgp-sig <sig>] [--committer <info>] [--root] [--root-revision] [--reply-to <event-id>]');
        $output->writeln('  sybil git-issue --repo-id <repo-id> --owner <pubkey> --subject <subject> --content <file> [--labels <labels>]');
        $output->writeln('  sybil git-status --event-id <event-id> --status <status> [--content <message>] [--repo-id <repo-id>] [--owner <pubkey>] [--revision-id <event-id>] [--merge-commit <id>] [--applied-commits <ids>]');
        $output->writeln('');

        $output->writeln('For more information, visit: https://github.com/Silberengel/sybil');

        return Command::SUCCESS;
    }

    private function displayGeneralHelp(OutputInterface $output): void
    {
        $output->writeln('Sybil - A Nostr event creation and publishing tool');
        $output->writeln('');
        $output->writeln('Usage: sybil <command> [arguments]');
        $output->writeln('');
        
        $output->writeln('Common Options:');
        $output->writeln('  --relay <relay_url>    Specify a relay URL');
        $output->writeln('  --protocol <ws|http>   Specify the protocol to use (default: ws, can be omitted)');
        $output->writeln('  --key <key_env_var>    Use a different private key (default: NOSTR_SECRET_KEY)');
        $output->writeln('  --json                 Output results in JSON format');
        $output->writeln('  --limit <number>       Limit the number of results');
        $output->writeln('  --force                Force an operation without confirmation');
        $output->writeln('');
        
        $output->writeln('Available commands:');
        $output->writeln('');
        
        // Content Creation Commands
        $output->writeln('Content Creation:');
        $output->writeln('  note');
        $output->writeln('    Post a text note');
        $output->writeln('    Usage: sybil note <content> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  longform');
        $output->writeln('    Create and publish a longform article');
        $output->writeln('    Usage: sybil longform <file_path> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  wiki');
        $output->writeln('    Create and publish a wiki article');
        $output->writeln('    Usage: sybil wiki <file_path> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  publication');
        $output->writeln('    Create and publish a publication');
        $output->writeln('    Usage: sybil publication <file_path> [--relay <relay_url>]');
        $output->writeln('');
        
        // Relay Management Commands
        $output->writeln('Relay Management:');
        $output->writeln('  relay-add');
        $output->writeln('    Add a new Nostr relay');
        $output->writeln('    Usage: sybil relay-add <relay> [--test]');
        $output->writeln('');
        
        $output->writeln('  relay-list');
        $output->writeln('    Display a list of known Nostr relays');
        $output->writeln('    Usage: sybil relay-list');
        $output->writeln('');
        
        $output->writeln('  relay-info');
        $output->writeln('    Display detailed information about a Nostr relay');
        $output->writeln('    Usage: sybil relay-info <relay>');
        $output->writeln('');
        
        $output->writeln('  relay-remove');
        $output->writeln('    Remove a Nostr relay');
        $output->writeln('    Usage: sybil relay-remove <relay> [--force]');
        $output->writeln('');
        
        $output->writeln('  relay-test');
        $output->writeln('    Test a Nostr relay\'s connectivity and features');
        $output->writeln('    Usage: sybil relay-test <relay>');
        $output->writeln('');
        
        // Event Interaction Commands
        $output->writeln('Event Interaction:');
        $output->writeln('  query');
        $output->writeln('    Query Nostr relays for events');
        $output->writeln('    Usage: sybil query -r <relay> [-k <kind>] [-a <author>] [-t <tag>] [-s <since>] [-u <until>] [-l <limit>] [--sync]');
        $output->writeln('');
        
        $output->writeln('  reply');
        $output->writeln('    Reply to a Nostr event');
        $output->writeln('    Usage: sybil reply <event_id> <content> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  republish');
        $output->writeln('    Republish a Nostr event');
        $output->writeln('    Usage: sybil republish <event_json> [--relay <relay_url>]');
        $output->writeln('');
        
        $output->writeln('  broadcast');
        $output->writeln('    Broadcast a Nostr event to multiple relays');
        $output->writeln('    Usage: sybil broadcast <event_json> [--relays <relay_urls>]');
        $output->writeln('');
        
        $output->writeln('  delete');
        $output->writeln('    Delete a Nostr event');
        $output->writeln('    Usage: sybil delete <event_id> [--reason <reason>]');
        $output->writeln('');
        
        // Information Commands
        $output->writeln('Information:');
        $output->writeln('  nip-info');
        $output->writeln('    Display information about Nostr Improvement Proposals');
        $output->writeln('    Usage: sybil nip-info [<nip>] [--category <category>] [--status <status>]');
        $output->writeln('');
        
        $output->writeln('  nkbip');
        $output->writeln('    Display information about Nostr Knowledge Base Improvement Proposals');
        $output->writeln('    Usage: sybil nkbip [<nkbip>] [--category <category>] [--status <status>]');
        $output->writeln('');
        
        $output->writeln('  help');
        $output->writeln('    Display help information');
        $output->writeln('    Usage: sybil help [<command>]');
        $output->writeln('');
        
        $output->writeln('For more information about Sybil, visit: https://github.com/Silberengel/sybil');
    }

    private function displayCommandHelp(string $command, OutputInterface $output): void
    {
        switch ($command) {
            case 'feed':
                $output->writeln('Command: feed');
                $output->writeln('');
                $output->writeln('Show a relay\'s feed with formatted output');
                $output->writeln('');
                $output->writeln('Usage: sybil feed -r <relay> [--protocol <ws|http>] [--limit <number>]');
                $output->writeln('');
                $output->writeln('Arguments:');
                $output->writeln('  -r, --relay');
                $output->writeln('    Relay URL to query (required)');
                $output->writeln('  --limit');
                $output->writeln('    Maximum number of events to show (default: 20)');
                $output->writeln('');
                $output->writeln('Examples:');
                $output->writeln('  sybil feed -r wss://relay.damus.io');
                $output->writeln('  sybil feed -r wss://relay.damus.io --limit 50');
                $output->writeln('');
                $output->writeln('Notes:');
                $output->writeln('  The feed command shows kind 1 (text note) events from the specified relay.');
                $output->writeln('  Events are sorted by creation time (newest first).');
                $output->writeln('  Author names are resolved from their metadata (kind 0) events.');
                $output->writeln('  URLs are formatted in blue and mentions in green.');
                break;

            default:
                $output->writeln(sprintf('No help available for command "%s"', $command));
                break;
        }
    }
} 
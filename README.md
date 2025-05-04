# Sybil

![Sybil](https://i.nostr.build/Jo7qwDu7rgYkMIWJ.png)

A command-line tool for creating and publishing Nostr events, managing relays, and interacting with the Nostr network.

## Installation

### Quick Install

```bash
# Clone the repository
git clone https://github.com/Silberengel/sybil.git
cd sybil

# Run the installation script
chmod +x bin/install.sh
./bin/install.sh
```

The installation script will:
1. Install PHP 8.1 and required extensions
2. Install Composer (if not already installed)
3. Install Sybil and its dependencies
4. Set up the `sybil` command alias
5. Configure your environment

After installation, restart your terminal or run:
```bash
source ~/.bashrc  # or source ~/.zshrc
```

### Manual Installation

If you prefer to install manually, see the [installation script](bin/install.sh) for detailed requirements and setup instructions.

## Development

### Running Tests

Sybil includes a comprehensive integration test suite. To run the tests:

```bash
# Run tests with text coverage report
composer test

# Run tests with HTML coverage report
composer test:coverage -- --verbose  # HTML coverage with verbose output

# Run tests with different verbosity levels
composer test -- --quiet        # Minimal output
composer test -- --verbose      # More detailed output
composer test -- --debug        # Most detailed output including debug logs
```

The tests use a local test relay (ws://localhost:8080) and a public relay (wss://realy.mleku.dev) for testing both WebSocket and HTTP protocols.

## Common Options

These options are available for all commands:

- `--relay <relay_url>`: Specify a relay URL
- `--protocol <ws|http>`: Specify the protocol to use (default: ws, can be omitted for WebSocket)
- `--key <key_env_var>`: Use a different private key from an environment variable (default: NOSTR_SECRET_KEY)
- `--json`: Output results in JSON format
- `--limit <number>`: Limit the number of results
- `--force`: Force an operation without confirmation

## Basic Usage

### Content Creation

```bash
# Post a text note
sybil note "Hello, Nostr!"
sybil note notecontent.txt
sybil note notecontent.md

# Create and publish a longform article
sybil longform article.md

# Create and publish a wiki article
sybil wiki wiki.adoc

# Create and publish a publication
sybil publication pub.adoc
```

### Relay Management

```bash
# Add a new relay
sybil relay-add wss://relay.example.com

# List all relays
sybil relay-list

# Get relay information
sybil relay-info wss://relay.example.com

# Test relay connectivity
sybil relay-test wss://relay.example.com

# Remove a relay
sybil relay-remove wss://relay.example.com
```

### Event Interaction

```bash
# Query events
sybil query -r wss://relay.example.com -k 1 -a <pubkey>

# Reply to an event
sybil reply <event_id> "My reply" --relay wss://relay.example.com

# Create a highlight
sybil highlight --event-id <event_id> --content "Highlighted text"
sybil highlight --url <url> --content "Highlighted text" --context "Surrounding context"
sybil highlight --content "Text to highlight" --author <pubkey> --role author
sybil highlight --event-id <event_id> --comment "My thoughts on this highlight"

# Republish an event
sybil republish <event_json> --relay wss://relay.example.com

# Broadcast an event
sybil broadcast <event_json> --relays "wss://relay1.example.com,wss://relay2.example.com"

# Delete an event
sybil delete <event_id> --reason "Reason for deletion"
```

### Highlight Command

The highlight command creates kind 9802 events to signal content you find valuable. It follows NIP-84 and supports:

- Highlighting Nostr events:
  ```bash
  sybil highlight --event-id <event_id> --content "Highlighted text"
  sybil highlight --event-id <event_id> --comment "My thoughts on this highlight"
  ```

- Highlighting URLs:
  ```bash
  sybil highlight --url <url> --content "Highlighted text"
  sybil highlight --url <url> --content "Highlighted text" --context "Surrounding context"
  ```

- Highlighting text content:
  ```bash
  sybil highlight --content "Text to highlight"
  cat file.txt | sybil highlight
  ```

Options:
- `--event-id` or `-e`: Event ID to highlight
- `--url` or `-u`: URL to highlight
- `--content` or `-c`: Highlighted text content
- `--context`: Surrounding context for the highlight
- `--comment`: Comment for quote highlight
- `--author` or `-a`: Author pubkey to attribute (can be used multiple times)
- `--role`: Role for author attribution (author|editor)
- `--relay` or `-r`: Relay URL to publish to
- `--json` or `-j`: Output in JSON format

The command automatically:
- Cleans URLs by removing tracking parameters
- Adds proper tag attributes for mentions and sources
- Handles quote highlights with comments
- Supports author attribution with roles
- Provides context for partial highlights

### Git Repository Management

```bash
# Announce a new repository
sybil git-announce --id my-project --name "My Project" --description "A cool project" \
    --web https://github.com/user/repo \
    --clone https://github.com/user/repo.git \
    --relays wss://relay1.example.com,wss://relay2.example.com \
    --maintainers npub1abc123def456, npub1xyz789uvw012 \
    --tags nostr,git

# Update repository state
sybil git-state --id my-project --refs-heads-main abc123 --refs-tags-v1.0 def456

# Submit a patch
sybil git-patch --repo-id my-project --owner <pubkey> --content patch.diff

# Create an issue
sybil git-issue --repo-id my-project --owner <pubkey> --subject "Bug in feature X" --content issue.md --labels bug,high-priority

# Update issue/patch status
sybil git-status --event-id <event_id> --status open|applied|closed|draft --content "Status update message"

# View git-related events in a threaded format
sybil git-feed --relay wss://relay.example.com --repo-id my-project --limit 20
```

## Feed Commands

Sybil provides several commands for viewing different types of Nostr content in a threaded format. Each feed command supports the following common options:

- `--relay` or `-r`: Relay URL to query (default: wss://relay.damus.io)
- `--limit` or `-l`: Maximum number of events to show (default: 20)
- `--since` or `-s`: Show events since timestamp
- `--until` or `-u`: Show events until timestamp
- `--json` or `-j`: Output in JSON format
- `--verbose` or `-v`: Show detailed information including timestamps, event kinds, and tags

### Note Feed

View kind 1 notes and their replies:

```bash
sybil note-feed [--relay <url>] [--author <pubkey>] [--limit <number>] [--json] [--verbose]
```

### Longform Feed

View longform articles (kind 30023) and their replies:

```bash
sybil longform-feed [--relay <url>] [--author <pubkey>] [--limit <number>] [--json] [--verbose]
```

### Publication Feed

View publications (kinds 30040, 30041) with their replies and highlights:

```bash
sybil publication-feed [--relay <url>] [--author <pubkey>] [--identifier <id>] [--type <index|content|all>] [--limit <number>] [--json] [--verbose]
```

Options:
- `--type` or `-t`: Filter by type (index|content|all, default: all)
- `--identifier` or `-i`: Filter by publication identifier
- Other common options apply

The feed displays:
- Publication index events (kind 30040)
- Publication content events (kind 30041)
- Replies to both types (kind 1111)
- Highlights of both types (kind 9802)

For publication content (30041) events, the feed shows:
- Whether it's part of one or more publications
- For each publication (up to 3 most recent):
  - Title
  - Author
  - Event ID
  - Creation timestamp
- The content of the event
- Replies and highlights in a threaded format

Each event shows:
- Event type and title
- Author name
- Event ID
- Content preview
- Replies and highlights in a threaded format

### Wiki Feed

View wiki content (kind 30030) and their replies:

```bash
sybil wiki-feed [--relay <url>] [--author <pubkey>] [--identifier <id>] [--limit <number>] [--json] [--verbose]
```

### Git Feed

View git-related events (kinds 30617, 30618, 1617, 1621, 1630-1633) and their replies:

```bash
sybil git-feed [--relay <url>] [--repo-id <id>] [--owner <pubkey>] [--limit <number>] [--json] [--verbose]
```

Each feed command displays events in a threaded format, showing:
- Event type and title
- Author name (resolved from metadata)
- Event ID (for referencing in replies)
- Content preview
- Status information (for git issues and patches)
- Replies in a threaded format

When using the `--verbose` flag, additional information is shown:
- Creation timestamp
- Event kind
- All event tags
- Full content of replies

The `--json` flag outputs the raw event data in JSON format, which can be useful for scripting or further processing.

## Support

For support, please:
1. Check the [documentation](https://next-alexandria.gitcitadel.eu/publication?d=sybil)
2. Open an [issue](https://gitcitadel.com/r/naddr1qvzqqqrhnypzplfq3m5v3u5r0q9f255fdeyz8nyac6lagssx8zy4wugxjs8ajf7pqythwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0qqz4x7tzd9kqftxaxq)
3. Contact the developer directly: [Silberengel on Nostr](https://gitcitadel.com/p/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z)
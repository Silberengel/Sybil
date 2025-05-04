# Command Reference Guide

This guide provides a complete reference of all Sybil commands, their options, and usage examples.

## Core Commands

### Query Command

```bash
sybil query [options]

Options:
  --authors PUBKEY          Filter by author public key
  --kinds KIND              Filter by event kind
  --since TIMESTAMP         Filter events after timestamp
  --until TIMESTAMP         Filter events before timestamp
  --limit NUMBER            Limit number of results
  --relay URL               Specify relay URL
  --raw                    Output in JSON format
  --verbose                 Enable verbose output
  --help                    Show help message

Examples:
  sybil query --authors <pubkey> --kinds 1
  sybil query --since 1234567890 --until 1234567899
  sybil query --limit 10 --raw
```

Search and filter Nostr events:
```bash
# Query recent notes from a specific relay
sybil query --relay wss://relay.damus.io

# Query with specific filters
sybil query --authors <pubkey> --kinds 1 --limit 10

# Find all interactions with a specific user
sybil query --authors <their_npub> --p <your_npub> --kinds 1,7,9735

# Find a specific event by ID
sybil query --ids <event_id>

# Find a specific event by naddr (NIP-19 address)
sybil query --naddr <naddr>

# Find all replies to a specific event
sybil query --e <event_id> --kinds 1

# Find all reactions to a specific event
sybil query --e <event_id> --kinds 7

# Find all zaps on a specific event
sybil query --e <event_id> --kinds 9735

# Find all events with specific tags
sybil query --t <tag_name> --v <tag_value>

# Find all events from a specific time period
sybil query --since 1704067200 --until 1704153600  # Last 24 hours
```

### Citation Management
Manage citations and references:
```bash
# Create an internal reference (kind 30)
sybil citation --type internal --title "Example Post" --author "John Doe" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --nostr-ref "1:pubkey:event_id" --relay-hint "wss://relay.example.com"

# Create a web reference (kind 31)
sybil citation --type web --title "Example Article" --author "Jane Smith" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --url "https://example.com/article"

# Create a hardcopy reference (kind 32)
sybil citation --type hardcopy --title "Example Book" --author "Bob Wilson" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --page-range "123-125" --published-by "Example Press"

# Create a prompt reference (kind 33)
sybil citation --type prompt --title "Example Prompt" --author "ChatGPT" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --llm "ChatGPT" --version "4.0"

```

### Note Command
```bash
sybil note <content> [options]

Options:
  --relay URL               Specify relay URL
  --raw                    Output in JSON format
  --help                    Show help message

Examples:
  sybil note "Hello, Nostr!"
  sybil note "Hello specific relay" --relay wss://relay.example.com
  sybil note "Hello with raw output" --raw
```

### Relay Management

#### List Relays
```bash
sybil relay-list [--raw]

Options:
  --raw                    Output in JSON format
  --help                   Show help message

Example:
  sybil relay-list
  sybil relay-list --raw
```

#### Add Relay
```bash
sybil relay-add <relay> [options]

Options:
  --test                   Test relay before adding
  --raw                    Output in JSON format
  --help                   Show help message

Example:
  sybil relay-add wss://relay.example.com
  sybil relay-add wss://relay.example.com --test
```

#### Remove Relay
```bash
sybil relay-remove <relay> [options]

Options:
  --force                  Remove without confirmation
  --raw                    Output in JSON format
  --help                   Show help message

Example:
  sybil relay-remove wss://relay.example.com
  sybil relay-remove wss://relay.example.com --force
```

#### Get Relay Info
```bash
sybil relay-info <relay> [options]

Options:
  --raw                    Output in JSON format
  --help                   Show help message

Example:
  sybil relay-info wss://relay.example.com
  sybil relay-info wss://relay.example.com --raw
```

#### Test Relay
```bash
sybil relay-test <relay> [options]

Options:
  --protocol TYPE          Protocol to use (ws or http)
  --raw                    Output in JSON format
  --help                   Show help message

Example:
  sybil relay-test wss://relay.example.com
  sybil relay-test wss://relay.example.com --protocol http
```

### Feed Commands

#### Note Feed
```bash
sybil note-feed [options]

Options:
  --relay URL              Relay URL to query
  --author PUBKEY          Filter by author
  --limit NUMBER           Maximum number of events to show (default: 20)
  --since TIMESTAMP        Show events since timestamp
  --until TIMESTAMP        Show events until timestamp
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil note-feed --relay wss://relay.example.com --limit 20
  sybil note-feed --author <pubkey> --verbose
```

#### Longform Feed
```bash
sybil longform-feed [options]

Options:
  --relay URL              Relay URL to query
  --author PUBKEY          Filter by author
  --limit NUMBER           Maximum number of events to show (default: 20)
  --since TIMESTAMP        Show events since timestamp
  --until TIMESTAMP        Show events until timestamp
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil longform-feed --relay wss://relay.example.com
  sybil longform-feed --author <pubkey> --verbose
```

#### Publication Feed
```bash
sybil publication-feed [options]

Options:
  --relay URL              Relay URL to query
  --author PUBKEY          Filter by author
  --identifier ID          Filter by publication identifier
  --type TYPE              Filter by type (index|content|all, default: all)
  --limit NUMBER           Maximum number of events to show (default: 20)
  --since TIMESTAMP        Show events since timestamp
  --until TIMESTAMP        Show events until timestamp
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil publication-feed --type content --author <pubkey>
  sybil publication-feed --identifier my-publication --type all
```

#### Wiki Feed
```bash
sybil wiki-feed [options]

Options:
  --relay URL              Relay URL to query
  --author PUBKEY          Filter by author
  --identifier ID          Filter by wiki identifier
  --limit NUMBER           Maximum number of events to show (default: 20)
  --since TIMESTAMP        Show events since timestamp
  --until TIMESTAMP        Show events until timestamp
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil wiki-feed --relay wss://relay.example.com
  sybil wiki-feed --identifier <wiki_id> --verbose
```

#### Ngit Feed
```bash
sybil ngit-feed [options]

Options:
  --relay URL              Relay URL to query
  --repo-id ID             Filter by repository ID
  --owner PUBKEY           Filter by repository owner
  --limit NUMBER           Maximum number of events to show (default: 20)
  --since TIMESTAMP        Show events since timestamp
  --until TIMESTAMP        Show events until timestamp
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil ngit-feed --relay wss://relay.example.com --repo-id my-project
  sybil ngit-feed --owner <pubkey> --limit 20
```

#### Citation Feed
```bash
sybil citation-feed [options]

Options:
  --relay URL              Relay URL to query
  --author PUBKEY          Filter by author
  --limit NUMBER           Maximum number of events to show (default: 10)
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil citation-feed --relay wss://relay.example.com
  sybil citation-feed --author <pubkey> --limit 10
```

### Citation Command
```bash
sybil citation [options]

Options:
  --type TYPE            Citation type (internal, web, hardcopy, prompt)
  --title TEXT           Title to display for citation
  --author TEXT          Author to display for citation
  --content TEXT         Text cited
  --summary TEXT         Short explanation of topics covered
  --accessed-on TEXT     Date-time accessed in ISO 8601 format
  --published-on TEXT    Date-time published in ISO 8601 format
  --location TEXT        Where it was written or published
  --geohash TEXT         Geohash of the precise location
  --relay URL            The relay URL to publish to
  --json                 Output raw event data

Type-specific options:
Internal reference (--type internal):
  --nostr-ref TEXT       Nostr reference (kind:pubkey:event_id)
  --relay-hint URL       Relay hint for internal reference

Web reference (--type web):
  --url URL              URL where citation was accessed
  --published-by TEXT    Who published the citation
  --version TEXT         Version or edition
  --open-timestamp TEXT  Open timestamp event ID

Hardcopy reference (--type hardcopy):
  --page-range TEXT      Pages the citation is found on
  --chapter-title TEXT   Chapter or section title
  --editor TEXT          Who edited the publication
  --published-in TEXT    Journal name and volume
  --doi TEXT            DOI number
  --version TEXT         Version or edition
  --published-by TEXT    Who published the citation

Prompt reference (--type prompt):
  --llm TEXT            Language model used for the prompt
  --version TEXT         Version or edition of the model
  --url URL             Website LLM was accessed from

Examples:
  # Internal reference (kind 30)
  sybil citation --type internal --title "Example Post" --author "John Doe" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --nostr-ref "1:pubkey:event_id" --relay-hint "wss://relay.example.com"

  # Web reference (kind 31)
  sybil citation --type web --title "Example Article" --author "Jane Smith" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --url "https://example.com/article"

  # Hardcopy reference (kind 32)
  sybil citation --type hardcopy --title "Example Book" --author "Bob Wilson" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --page-range "123-125" --published-by "Example Press"

  # Prompt reference (kind 33)
  sybil citation --type prompt --title "Example Prompt" --author "ChatGPT" --content "Cited text" --accessed-on "2024-03-20T12:00:00Z" --llm "ChatGPT" --version "4.0"
```

### Convert Command

```bash
sybil convert <input_file> [options]

Options:
  --output, -o    Output file path (defaults to input.adoc)
  --title, -t     Document title (defaults to filename)
  --force, -f     Force overwrite of existing output file

Examples:
  sybil convert document.md
  sybil convert document.md -o output.adoc -t "My Document"
  sybil convert document.md -f
```


### Nostr Git Commands (ngit)

These commands handle git-related events on Nostr (NIP-34). They are separate from regular git commands.

#### Ngit Patch
```bash
sybil ngit-patch [options]

Options:
  --relay URL              Relay URL to query
  --repo-id ID             Repository ID
  --title TEXT             Patch title
  --description TEXT       Patch description
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil ngit-patch --repo-id my-project --title "Fix bug" --description "Fixes issue #123"
```

#### Ngit Status
```bash
sybil ngit-status [options]

Options:
  --relay URL              Relay URL to query
  --repo-id ID             Repository ID
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil ngit-status --repo-id my-project
```

#### Ngit State
```bash
sybil ngit-state [options]

Options:
  --relay URL              Relay URL to query
  --repo-id ID             Repository ID
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil ngit-state --repo-id my-project
```

#### Ngit Issue
```bash
sybil ngit-issue [options]

Options:
  --relay URL              Relay URL to query
  --repo-id ID             Repository ID
  --title TEXT             Issue title
  --description TEXT       Issue description
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil ngit-issue --repo-id my-project --title "Bug report" --description "Found a bug in..."
```

#### Ngit Announce
```bash
sybil ngit-announce [options]

Options:
  --relay URL              Relay URL to query
  --repo-id ID             Repository ID
  --title TEXT             Announcement title
  --description TEXT       Announcement description
  --json                   Output in JSON format
  --verbose                Show detailed information
  --help                   Show help message

Example:
  sybil ngit-announce --repo-id my-project --title "New release" --description "Version 1.0.0 is out!"
```

Return to the [Read Me](./../README.md)
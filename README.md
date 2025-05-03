# Sybil

A command-line tool for creating and publishing Nostr events.

> ⚠️ **Warning:** Please defer to tagged versions, as I only have one branch and it's sometimes a construction site.

## Overview

Sybil is a command-line tool that helps you create and publish various types of Nostr events, including:
- Text notes
- Longform articles
- Wiki pages
- Publications

It provides a simple interface for interacting with Nostr relays and managing your events.

## Installation

```bash
composer require sybil/sybil
```

## Setup

Before using Sybil, you need to set up your Nostr secret key:

1. Set the NOSTR_SECRET_KEY environment variable:
   ```bash
   # Linux/macOS
   export NOSTR_SECRET_KEY=nsec1...
   
   # Windows
   set NOSTR_SECRET_KEY=nsec1...
   ```

   Note: You can also use a hex key - it will be automatically converted:
   ```bash
   # Linux/macOS
   export NOSTR_SECRET_KEY=0123456789abcdef...
   
   # Windows
   set NOSTR_SECRET_KEY=0123456789abcdef...
   ```

2. For permanent setup:
   - Linux/macOS: Add to ~/.bashrc or ~/.zshrc
   - Windows: Add to System Environment Variables

3. Using multiple keys:
   - You can use different environment variables for different keys
   - Example: `export SYBIL_KEY_1=nsec1...`
   - Or with hex: `export SYBIL_KEY_1=0123456789abcdef...`
   - Then use: `sybil note "Hello" --key SYBIL_KEY_1`

Note: Your secret key (nsec or hex) is used to sign events. Keep it secure!

## Relay Configuration

Sybil uses relays to publish and fetch events. You can manage relays in two ways:

1. Using the --relay option:
   - Specify a single relay for a command: `--relay wss://relay.example.com`
   - Example: `sybil note "Hello" --relay wss://relay.example.com`

2. Using the relays.yml file:
   - Create or edit user/relays.yml to specify your preferred relays
   - One relay URL per line
   - Example relays:
     ```
     wss://relay.damus.io
     wss://relay.nostr.band
     wss://nos.lol
     ```

If no relay is specified, Sybil will use these default relays:
```
wss://thecitadel.nostr1.com
wss://relay.damus.io
wss://relay.nostr.band
wss://nostr.einundzwanzig.space
wss://relay.primal.net
wss://nos.lol
wss://relay.lumina.rocks
wss://freelay.sovbit.host
wss://wheat.happytavern.co
wss://nostr21.com
wss://theforest.nostr1.com
```

## Usage

For detailed usage information, including output redirection, logging levels, and command-specific help, run:
```bash
sybil help
```

For help on a specific command:
```bash
sybil help <command>
```

The help system provides comprehensive information about:
- Output redirection and piping
- Logging levels and configuration
- Relay management
- Command-specific usage and examples

## Support

Project repository:
https://gitcitadel.com/r/naddr1qvzqqqrhnypzplfq3m5v3u5r0q9f255fdeyz8nyac6lagssx8zy4wugxjs8ajf7pqythwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0qqz4x7tzd9kqftxaxq

For support or questions, contact the developer at:
https://njump.me/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z
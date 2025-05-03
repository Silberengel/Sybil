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

3. Checking relay information:
   - Use the `relay-info` command to get detailed information about a relay
   - Example: `sybil relay-info wss://relay.example.com`
   - Shows supported NIPs, limitations, fees, and authentication support

4. Authentication (NIP-42):
   - Sybil automatically handles authentication with relays that support NIP-42
   - Authentication is enabled by default when sending events
   - You can check if a relay supports authentication using `relay-info`
   - Example: `sybil relay-info wss://relay.example.com`

If no relay is specified, Sybil will use these default relays:
```
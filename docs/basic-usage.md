# Basic Usage Guide

This guide covers the fundamental commands and features of Sybil. For more detailed information about specific features, refer to the corresponding documentation files.

## Command Structure

Sybil commands follow this general structure:
```bash
sybil <command> [options] [arguments]
```

## Available Commands

### Note Command
Create and publish notes to Nostr:
```bash
# Create a simple note
sybil note "Your message here"

# Create a note with specific relay
sybil note --relay wss://relay.example.com "Your message here"

# Create a note with raw output
sybil note --raw "Your message here"
```

### Help and Information

Get help and information about Sybil:
```bash
# Show main help with topics
sybil help

# Show help for a specific topic
sybil help article
sybil help relay
sybil help git
sybil help citation
sybil help utility

# Show help for a specific command
sybil help note
sybil help publication
sybil help citation

# Show all command details
sybil help --all

# Show version information
sybil version
```

## Common Options

All commands support these common options:
```bash
--help                    Show help message
--verbose                 Enable verbose output
--raw                    Output in JSON format
--relay URL               Specify relay URL
--protocol TYPE           Protocol type, websocket or https (ws or http)
```

## Command Completion

Sybil provides command completion for both bash and zsh. After installation, you can use:
```bash
# Command name completion
sybil no<TAB>  # Suggests: sybil note

# Option completion
sybil note --<TAB>  # Shows available options

# File completion
sybil note <TAB>  # Shows available files
```

## Environment Variables

Sybil uses these environment variables:
```bash
NOSTR_SECRET_KEY    Your Nostr private key (public key is derived from this)
SYBIL_CONFIG_PATH   Path to configuration file
```

## Configuration Files

Sybil uses two configuration files:

1. `config/app.php` - Main application configuration
2. `config/relays.php` - Relay configuration

Return to the [Read Me](./../README.md)
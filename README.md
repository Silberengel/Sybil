# Sybil

![Sybil gazing into a crystal ball](https://i.nostr.build/Jo7qwDu7rgYkMIWJ.png)

A tool for creating and publishing Nostr events.

## Overview

Sybil is a command-line utility for creating and publishing various types of Nostr events, including publications, longform articles, wiki pages, and text notes. It also provides utilities for fetching, broadcasting, and deleting events.

## Installation

### Requirements

- PHP 8.0 or higher
- Composer
- PHP YAML extension

### Installation Steps

1. Clone the repository:

```bash
git clone https://github.com/silberengel/sybil.git
cd sybil
```

2. Install dependencies:

```bash
composer install
```

3. Make the executable available globally (optional):

```bash
composer global require sybil/sybil
```

After global installation, you can run the command simply as:

```bash
sybil publication path/to/file.adoc
```

Alternatively, you can create a symlink to the executable in a directory that's in your PATH:

```bash
# For Linux/macOS
ln -s "$(pwd)/bin/sybil" /usr/local/bin/sybil

# For Windows (PowerShell, run as Administrator)
# New-Item -ItemType SymbolicLink -Path "C:\Windows\System32\sybil.bat" -Target "$((Get-Location).Path)\bin\sybil.bat"
```

## Configuration

### Relay Configuration

Relays are configured in `config/relays.php`. You can modify this file to add or remove relays.

### User Relays

User-specific relays can be configured in `user/relays.yml`. This file should contain a list of relay URLs, one per line.

### Nostr Private Key

Sybil requires a Nostr private key to sign events. Set the `NOSTR_SECRET_KEY` environment variable to your nsec key:

```bash
export NOSTR_SECRET_KEY=nsec19371...
```

## Usage

### Directions

To find out how it works, simply type `sybil` and <ENTER> on the command line, and an extensive help will open.

For detailed results, use the `--verbose` flag:

```bash
sybil delete <event-id> --verbose
```

> **Note:** If you haven't installed Sybil globally or created a symlink, you'll need to use `bin/sybil` instead of just `sybil` in the commands above.

## File Format

### User-defined tags

AsciiDoc and Markdown files can include YAML metadata at the beginning of the file, enclosed in `<<YAML>>` and `<</YAML>>` tags. The templates for that are located in `./user`. Here is a publication example snippet:

```
= Document Header
<<YAML>>
title: My Publication
author: John Doe
version: 1.0
tags:
  - ["t", "nostr"]
  - ["t", "publication"]
<</YAML>>

== This is the first section header

This is the content of my first section.
```

## Test

If you run a relay on your machine, on ws://localhost:8080, then you can run the integration test by calling:

```bash
./vendor/bin/phpunit --testsuite Integration
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.

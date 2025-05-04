# Sybil

![Sybil](https://i.nostr.build/Jo7qwDu7rgYkMIWJ.png)

A powerful Nostr CLI tool for creating and managing various types of Nostr events. I built this in order to test the [Alexandria web app.](https://next-alexandria.gitcitadel.eu/about) It uses the [PHP Helper](https://gitcitadel.com/r/naddr1qvzqqqrhnypzqpnrnguxe8qszsshvgkvhn6qjzxy7xsvx03rlrtddr62haj4lrm3qy88wumn8ghj7mn0wvhxcmmv9uqqjmn0wd68yttsdpcqa874nw) library, as well as Monolog, Doctrine, and Symfony Console.

**Please note that only tagged versions work, as I build on the master branch.**

## Quick Start

```bash
# Install Sybil
curl -sSL https://raw.githubusercontent.com/silberengel/sybil/main/bin/install.sh | bash

# Set your Nostr key
export NOSTR_SECRET_KEY=your_private_key_here

# Create a note
sybil note "Hello, Nostr!"
```

## Documentation

- [Installation Guide](docs/installation.md)
- [Basic Usage](docs/basic-usage.md)
- [Event Types](docs/event-types.md)
- [Relay Authentication](docs/relay-authentication.md)
- [MIME Types and Categories](docs/mime-types.md)
- [Command Reference](docs/command-reference.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Scriptorium Converter](docs/scriptorium.md)

## Support

For support, please:
1. Check the [Sybil wiki page](https://next-alexandria.gitcitadel.eu/publication?d=sybil)
2. Open an [issue](https://gitcitadel.com/r/naddr1qvzqqqrhnypzplfq3m5v3u5r0q9f255fdeyz8nyac6lagssx8zy4wugxjs8ajf7pqythwumn8ghj7un9d3shjtnwdaehgu3wvfskuep0qqz4x7tzd9kqftxaxq)
3. Contact the developer directly: [Silberengel on Nostr](https://gitcitadel.com/p/npub1l5sga6xg72phsz5422ykujprejwud075ggrr3z2hwyrfgr7eylqstegx9z)

## License

GNU General Public License v3.0 - see [LICENSE](LICENSE) for details.
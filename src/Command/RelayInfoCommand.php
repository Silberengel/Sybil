<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;
use Sybil\Utilities\RelayUtility;
use InvalidArgumentException;

/**
 * Command for displaying relay information
 * 
 * This command handles the 'relay-info' command, which fetches and displays
 * information about a Nostr relay using NIP-11.
 * Usage: sybil relay-info <relay_url>
 */
class RelayInfoCommand extends BaseCommand
{
    /**
     * Mapping of NIP numbers to their names
     */
    private const NIP_NAMES = [
        1 => 'Basic protocol flow description',
        2 => 'Follow List',
        3 => 'OpenTimestamps Attestations for Events',
        4 => 'Encrypted Direct Message (deprecated in favor of NIP-17)',
        5 => 'Mapping Nostr keys to DNS-based internet identifiers',
        6 => 'Basic key derivation from mnemonic seed phrase',
        7 => 'window.nostr capability for web browsers',
        8 => 'Handling Mentions (deprecated in favor of NIP-27)',
        9 => 'Event Deletion Request',
        10 => 'Text Notes and Threads',
        11 => 'Relay Information Document',
        13 => 'Proof of Work',
        14 => 'Subject tag in text events',
        15 => 'Nostr Marketplace (for resilient marketplaces)',
        17 => 'Private Direct Messages',
        18 => 'Reposts',
        19 => 'bech32-encoded entities',
        21 => 'nostr: URI scheme',
        22 => 'Comment',
        23 => 'Long-form Content',
        24 => 'Extra metadata fields and tags',
        25 => 'Reactions',
        26 => 'Delegated Event Signing (unrecommended)',
        27 => 'Text Note References',
        28 => 'Public Chat',
        29 => 'Relay-based Groups',
        30 => 'Custom Emoji',
        31 => 'Dealing with Unknown Events',
        32 => 'Labeling',
        34 => 'git stuff',
        35 => 'Torrents',
        36 => 'Sensitive Content',
        37 => 'Draft Events',
        38 => 'User Statuses',
        39 => 'External Identities in Profiles',
        40 => 'Expiration Timestamp',
        42 => 'Authentication of clients to relays',
        44 => 'Encrypted Payloads (Versioned)',
        45 => 'Counting results',
        46 => 'Nostr Remote Signing',
        47 => 'Nostr Wallet Connect',
        48 => 'Proxy Tags',
        49 => 'Private Key Encryption',
        50 => 'Search Capability',
        51 => 'Lists',
        52 => 'Calendar Events',
        53 => 'Live Activities',
        54 => 'Wiki',
        55 => 'Android Signer Application',
        56 => 'Reporting',
        57 => 'Lightning Zaps',
        58 => 'Badges',
        59 => 'Gift Wrap',
        60 => 'Cashu Wallet',
        61 => 'Nutzaps',
        62 => 'Request to Vanish',
        64 => 'Chess (PGN)',
        65 => 'Relay List Metadata',
        66 => 'Relay Discovery and Liveness Monitoring',
        68 => 'Picture-first feeds',
        69 => 'Peer-to-peer Order events',
        70 => 'Protected Events',
        71 => 'Video Events',
        72 => 'Moderated Communities',
        73 => 'External Content IDs',
        75 => 'Zap Goals',
        78 => 'Application-specific data',
        84 => 'Highlights',
        86 => 'Relay Management API',
        88 => 'Polls',
        89 => 'Recommended Application Handlers',
        90 => 'Data Vending Machines',
        92 => 'Media Attachments',
        94 => 'File Metadata',
        96 => 'HTTP File Storage Integration',
        98 => 'HTTP Auth',
        99 => 'Classified Listings',
        176 => 'Threads',
        180 => 'Web Bookmarks',
        183 => 'Blossom',
        192 => 'Code Snippets',
        199 => 'Chats'
    ];

    /**
     * Constructor
     *
     * @param Application $app The application instance
     */
    public function __construct(Application $app)
    {
        parent::__construct($app);
        
        $this->name = 'relay-info';
        $this->description = 'Display information about a Nostr relay';
        $this->help = <<<HELP
Display information about a Nostr relay using NIP-11.

Usage:
  sybil relay-info <relay_url> [--debug]

Arguments:
  relay_url    The WebSocket URL of the relay (must start with ws:// or wss://)

Options:
  --debug      Enable debug mode to show detailed connection and response information

Examples:
  sybil relay-info wss://relay.example.com
  sybil relay-info ws://localhost:8080 --debug

Debug Mode:
  When --debug is enabled, the command will show:
  - The URLs being tried for NIP-11 information
  - HTTP response codes
  - Raw response data
  - Any connection or parsing errors

Note:
  The command will try multiple common NIP-11 endpoint locations:
  - /.well-known/nostr.json
  - /api/nip11
  - /api/info
  - /info
HELP;
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
            if (!$this->validateArgs($args, 1, "Please provide a relay URL")) {
                return 1;
            }
            
            $relayUrl = $args[0];
            if (!preg_match('/^wss?:\/\/.+/', $relayUrl)) {
                $this->logger->error("Invalid relay URL. Must start with ws:// or wss://");
                return 1;
            }
            
            $this->logger->info("Fetching relay information for {$relayUrl}");
            
            // Pass our logger to RelayUtility to ensure consistent log levels
            RelayUtility::setLogger($this->logger);
            
            // Only pass debug=true to getRelayInfo if we're in debug mode
            $relayInfo = RelayUtility::getRelayInfo($relayUrl, $this->logger->getLogLevel() === LoggerService::LOG_LEVEL_DEBUG);
            if ($relayInfo === null) {
                $this->logger->error("Failed to fetch relay information");
                return 1;
            }
            
            // Only show formatted output if we're not in debug mode
            if ($this->logger->getLogLevel() !== LoggerService::LOG_LEVEL_DEBUG) {
                // Basic Information
                $this->logger->output("\nBasic Information:");
                $this->logger->output("  Name: " . ($relayInfo['name'] ?? 'N/A'));
                
                // Show more details in info mode
                if ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                    $this->logger->output("  Description: " . ($relayInfo['description'] ?? 'N/A'));
                    $this->logger->output("  Contact: " . ($relayInfo['contact'] ?? 'N/A'));
                    $this->logger->output("  Software: " . ($relayInfo['software'] ?? 'N/A'));
                    $this->logger->output("  Version: " . ($relayInfo['version'] ?? 'N/A'));
                    
                    // Show icon and payments URL in info mode
                    if (isset($relayInfo['icon'])) {
                        $this->logger->output("  Icon: " . $relayInfo['icon']);
                    }
                    if (isset($relayInfo['payments_url'])) {
                        $this->logger->output("  Payments URL: " . $relayInfo['payments_url']);
                    }
                }
                
                // Supported NIPs
                if (isset($relayInfo['supported_nips'])) {
                    $this->logger->output("\nSupported NIPs:");
                    sort($relayInfo['supported_nips']);
                    foreach ($relayInfo['supported_nips'] as $nip) {
                        $name = self::NIP_NAMES[$nip] ?? 'Unknown';
                        $this->logger->output("  NIP-{$nip}: {$name}");
                    }
                }
                
                // Limitations
                if (isset($relayInfo['limitation'])) {
                    $this->logger->output("\nLimitations:");
                    foreach ($relayInfo['limitation'] as $key => $value) {
                        // Show critical limitations in error mode
                        if (in_array($key, ['max_message_length', 'max_subscriptions', 'max_filters', 'max_limit'])) {
                            $this->logger->output("  {$key}: " . json_encode($value));
                        }
                        // Show all limitations in info mode
                        elseif ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                            $this->logger->output("  {$key}: " . json_encode($value));
                        }
                    }
                }
                
                // Fees
                if (isset($relayInfo['fees'])) {
                    $this->logger->output("\nFees:");
                    foreach ($relayInfo['fees'] as $feeType => $fee) {
                        // Handle nested fee arrays
                        if (is_array($fee) && isset($fee[0])) {
                            foreach ($fee as $subFee) {
                                if (isset($subFee['amount']) || isset($subFee['unit'])) {
                                    $amount = $subFee['amount'] ?? 'N/A';
                                    $unit = $subFee['unit'] ?? 'N/A';
                                    // Convert msats to sats
                                    if ($unit === 'msats') {
                                        $amount = $amount / 1000;
                                        $unit = 'sats';
                                    }
                                    $this->logger->output("  {$feeType}: {$amount} {$unit}");
                                } elseif ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                                    $this->logger->output("  {$feeType}: " . json_encode($subFee));
                                }
                            }
                        }
                        // Handle direct fee objects
                        else {
                            if (isset($fee['amount']) || isset($fee['unit'])) {
                                $amount = $fee['amount'] ?? 'N/A';
                                $unit = $fee['unit'] ?? 'N/A';
                                // Convert msats to sats
                                if ($unit === 'msats') {
                                    $amount = $amount / 1000;
                                    $unit = 'sats';
                                }
                                $this->logger->output("  {$feeType}: {$amount} {$unit}");
                            } elseif ($this->logger->getLogLevel() <= LoggerService::LOG_LEVEL_INFO) {
                                $this->logger->output("  {$feeType}: " . json_encode($fee));
                            }
                        }
                    }
                }
                
                // Authentication
                if (isset($relayInfo['supported_nips']) && in_array(42, $relayInfo['supported_nips'])) {
                    $this->logger->output("\nAuthentication: Supported (NIP-42)");
                } else {
                    $this->logger->output("\nAuthentication: Not supported");
                }
            }
            
            return 0;
        }, $args);
    }
} 
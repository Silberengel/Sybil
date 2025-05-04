<?php

/**
 * Relay configuration
 * 
 * This file contains the configuration for the relays used by the application.
 */
return [
    // Default relay for websocket connections
    'default_ws' => 'wss://freelay.sovbit.host',
    // Default relay for HTTP connections
    'default_http' => 'wss://realy.mleku.dev',
    // Default relay for feeds
    'default_feed' => 'wss://theforest.nostr1.com',
    
    // Local development relay
    'local' => 'ws://localhost:8080',
    
    // Default relays for most event kinds
    'relays' => [
        'wss://relay.damus.io',
        'wss://relay.nostr.band',
        'wss://nostr.einundzwanzig.space',
        'wss://relay.primal.net',
        'wss://nos.lol',
        'wss://freelay.sovbit.host',
        'wss://nostr21.com',
        'wss://nostr.wine',
        'wss://nostr.land',
        'wss://realy.mleku.dev'
    ],
    
    // Path to the user's relay configuration file
    'user_relays_file' => getcwd() . '/config/relays.yml'
];

<?php

/**
 * Relay configuration
 * 
 * This file contains the configuration for the relays used by the application.
 */
return [
    // Default relay for most event kinds
    'default' => 'wss://thecitadel.nostr1.com',
    
    // Default relay for kind 1 (text notes) and kind 1111 comments
    'kind1_default' => 'wss://freelay.sovbit.host',
    
    // Default relays for most event kinds
    'relays' => [
        'wss://thecitadel.nostr1.com',
        'wss://relay.damus.io',
        'wss://relay.nostr.band',
        'wss://nostr.einundzwanzig.space',
        'wss://relay.primal.net',
        'wss://nos.lol',
        'wss://relay.lumina.rocks',
        'wss://freelay.sovbit.host',
        'wss://wheat.happytavern.co',
        'wss://nostr21.com'
    ],
    
    // Default relays for kind 1 (text notes)
    'kind1_relays' => [
        'wss://freelay.sovbit.host',
        'wss://relay.damus.io',
        'wss://relay.nostr.band',
        'wss://nos.lol',
        'wss://theforest.nostr1.com'
    ],
    
    // Path to the user's relay configuration file
    'user_relays_file' => getcwd() . '/user/relays.yml'
];

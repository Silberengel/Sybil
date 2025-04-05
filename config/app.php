<?php

/**
 * Application configuration
 * 
 * This file contains general configuration settings for the application.
 */
return [
    // Application name
    'name' => 'Sybil',
    
    // Application version
    'version' => '1.0.0',
    
    // Default event kinds
    'event_kinds' => [
        'publication' => '30040',
        'section' => '30041',
        'longform' => '30023',
        'wiki' => '30818',
        'text_note' => '1',
        'deletion' => '5',
    ],
    
    // Path to the events log file
    'events_log_file' => getcwd() . "/eventsCreated.yml",
    
    // Environment variable name for the Nostr secret key
    'nostr_secret_key_env' => 'NOSTR_SECRET_KEY',
    
    // Default relay URL
    'default' => 'ws://localhost:8080',
];

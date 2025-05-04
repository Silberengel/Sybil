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
    'version' => '1.2.0',
    
    // Path to the events log file
    'events_log_file' => getcwd() . "/eventsCreated.yml",
    
    // Environment variable name for the Nostr secret key
    'nostr_secret_key_env' => 'NOSTR_SECRET_KEY',
];

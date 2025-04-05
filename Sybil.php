<?php
/**
 * Sybil Test Utility
 * 
 * This script creates and publishes Nostr events from AsciiDoc files.
 * It processes the input file, extracts metadata and content sections,
 * and creates the necessary publication and section events.
 * 
 * It also publishes longform and wiki notes, simple text notes, and has a set of basic Nostr utilities.
 * 
 * Usage: php Sybil.php <command> <path-to-asciidoc-file-or-content> [relay-url]
 * 
 * It recognizes the commands:
 * publication, longform, wiki, note, fetch, delete, and broadcast.
 * 
 * For the 'note' command, the second argument is the content of the note,
 * and an optional third argument can be provided to specify a relay URL.
 * Example: php Sybil.php note "Hello, Nostr world!" wss://relay.example.com
 * 
 */

include_once __DIR__.'/vendor/autoload.php';
include_once 'src/BaseEvent.php';
include_once 'src/PublicationEvent.php';
include_once 'src/LongformEvent.php';
include_once 'src/WikiEvent.php';
include_once 'src/TextNoteEvent.php';
include_once 'src/Tag.php';
include_once 'src/SectionEvent.php';
include_once 'src/HelperFunctions.php';
include_once 'src/Utilities.php';

echo PHP_EOL;

$command = $argv[1];
$secondArg = $argv[2];

    if(!$command){
        throw new InvalidArgumentException(
            PHP_EOL.'The command argument is missing.'.PHP_EOL.PHP_EOL);
    }
    if(!$secondArg){
        throw new InvalidArgumentException(
            PHP_EOL.'The second argument is missing. 
            It should be a filename or a hex event ID.'.PHP_EOL.PHP_EOL);
    }
    
    if(str_contains($command, 'publication') || str_contains($command, 'longform') || str_contains($command, 'wiki') || str_contains($command, 'note')){
    
        if(str_contains($command, 'publication')){
            // Define publication
            $publication = new PublicationEvent();
            // read in settings file argument passed
            $publication->setFile($secondArg);
            if (empty($publication->getFile())) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write publication into events
            try {
                $publication->publish();
                echo "The publication has been written." . PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }

        }
        
        if(str_contains($command, 'longform')){
            // Define longform article
            $longform = new LongformEvent();
            // read in settings file argument passed
            $longform->setFile($secondArg);
            if (empty($longform->getFile())) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write longform article into events
            try {
                $longform->publish();
                echo "The longform article has been written." . PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }
        }

        if(str_contains($command, 'wiki')){
            // Define wiki page
            $wiki = new WikiEvent();
            // read in settings file argument passed
            $wiki->setFile($secondArg);
            if (empty($wiki->getFile())) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write wiki into events
            try {
                $wiki->publish();
                echo "The wiki page has been written." . PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }
        }

        if(str_contains($command, 'note')){
            // Define text note
            $textNote = new TextNoteEvent($secondArg);
            
            // Check if a third argument was provided for the relay URL
            $relayUrl = isset($argv[3]) ? $argv[3] : '';
            
            // Write text note into events
            try {
                if (!empty($relayUrl)) {
                    // Publish to a specific relay
                    $result = $textNote->publishToRelay($relayUrl);
                } else {
                    // Publish to all relays in relays.yml
                    $textNote->publish();
                }
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }
        }
    } 
    else if(str_contains($command, 'fetch') || str_contains($command, 'delete') || str_contains($command, 'broadcast')){
        // Define utility
        $utility = new Utilities();
        $utility->setEventID($secondArg);

        // Call the appropriate utility
        try {
            $result = $utility->run_utility($command);
            
            // For fetch command, display the event data
            if ($command === 'fetch' && !empty($result)) {
                echo PHP_EOL.json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
            }
            
            // For delete command, display the verification message
            if ($command === 'delete' && !empty($result) && isset($result['verification'])) {
                echo PHP_EOL.$result['verification']['message'].PHP_EOL;
                
                // If detailed results are requested, display the full result
                if (isset($argv[3]) && $argv[3] === '--verbose') {
                    echo PHP_EOL."Detailed results:".PHP_EOL;
                    echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
                }
            }
            
            echo PHP_EOL."The utility run has finished.".PHP_EOL.PHP_EOL;
        } catch (Exception $e) {
            echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
        }
    } 
    else {
        throw new InvalidArgumentException(
            PHP_EOL.'That is not a valid command.'.PHP_EOL.PHP_EOL);
    }

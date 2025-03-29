<?php
/**
 * Sybil Test Utility
 * 
 * This script creates and publishes Nostr events from AsciiDoc files.
 * It processes the input file, extracts metadata and content sections,
 * and creates the necessary publication and section events.
 * 
 * It also publishes longform and wiki notes and has a set of basic Nostr utilities.
 * 
 * Usage: php Sybil.php <command> <path-to-asciidoc-file>
 * 
 * It recognizes the commands:
 * publication, longform, wiki, fetch, delete, and broadcast.
 * 
 */

include_once __DIR__.'/vendor/autoload.php';
include_once 'src/PublicationEvent.php';
include_once 'src/Tag.php';
include_once 'src/SectionEvent.php';
include_once 'src/HelperFunctions.php';
include_once 'src/Utilities.php';

echo PHP_EOL;

$command = $argv[1];
$secondArg = $arg[2];

    if(!$command){
        throw new InvalidArgumentException(
            PHP_EOL.'The command argument is missing.'.PHP_EOL.PHP_EOL);
    }
    if(!$secondArg){
        throw new InvalidArgumentException(
            PHP_EOL.'The second argument is missing. 
            It should be a filename or a hex event ID.'.PHP_EOL.PHP_EOL);
    }
    
    if(str_contains($command, 'publication' ||'longform' || 'wiki')){
    
        if(str_contains($command, 'publication')){
            // Define publication
            $publication = new PublicationEvent();
            // read in settings file argument passed
            $publication->file = $secondArg;
            if (empty($publication->file)) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write publication into events
            try {
                $publication->publish();
                echo PHP_EOL."The publication has been written.".PHP_EOL.PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }

        if(str_contains($command, 'longform')){
            // Define longform article
            $longform = new LongformEvent();
            // read in settings file argument passed
            $longform->file = $secondArg;
            if (empty($longform->file)) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write longform article into events
            try {
                $publication->publish();
                echo PHP_EOL."The longform article has been written.".PHP_EOL.PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }
        }

        if(str_contains($command, 'wiki')){
            // Define wiki page
            $wiki = new WikiEvent();
            // read in settings file argument passed
            $wiki->file = $secondArg;
            if (empty($wiki->file)) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write wiki into events
            try {
                $wiki->publish();
                echo PHP_EOL."The wiki page has been written.".PHP_EOL.PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }
        }
    } else {
        throw new InvalidArgumentException(
            PHP_EOL.'That is not a valid event type.'.PHP_EOL.PHP_EOL);
    }
    
    if(str_contains($command, 'fetch' ||'delete' || 'broadcast')){
        // Define utility
        $utility = new Utilities();
        $utility->eventID = $secondArg;

        // Call the appropriate utility
        try {
            $utility->run_utility($command);
            echo PHP_EOL."The utility run has finished.".PHP_EOL.PHP_EOL;
        } catch (Exception $e) {
            echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
        }
    } else {
        throw new InvalidArgumentException(
            PHP_EOL.'That is not a valid command.'.PHP_EOL.PHP_EOL);
    }
    
}
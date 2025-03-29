<?php
/**
 * Test version of Sybil.php for integration tests
 * 
 * This script is a modified version of Sybil.php that includes mock helper functions
 * to avoid actual network connections during tests.
 */

// Include mock helper functions first to override the original functions
include_once __DIR__ . '/MockHelperFunctions.php';

include_once dirname(__DIR__, 2) . '/vendor/autoload.php';
include_once dirname(__DIR__) . '/BaseEvent.php';
include_once dirname(__DIR__) . '/PublicationEvent.php';
include_once dirname(__DIR__) . '/LongformEvent.php';
include_once dirname(__DIR__) . '/WikiEvent.php';
include_once dirname(__DIR__) . '/Tag.php';
include_once dirname(__DIR__) . '/SectionEvent.php';
// Don't include the original HelperFunctions.php to avoid function redefinition
// include_once dirname(__DIR__) . '/HelperFunctions.php';
include_once dirname(__DIR__) . '/Utilities.php';

// Include mock event classes
include_once __DIR__ . '/MockLongformEvent.php';
include_once __DIR__ . '/MockWikiEvent.php';

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
    
    if(str_contains($command, 'publication') || str_contains($command, 'longform') || str_contains($command, 'wiki')){
    
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
                echo PHP_EOL."The publication has been written.".PHP_EOL.PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }

        }
        
        if(str_contains($command, 'longform')){
            // Define longform article using the mock class
            $longform = new MockLongformEvent();
            // read in settings file argument passed
            $longform->setFile($secondArg);
            if (empty($longform->getFile())) {
                throw new InvalidArgumentException(
                    PHP_EOL.'The source file argument is missing.'.PHP_EOL.PHP_EOL);
            }

            // Write longform article into events
            try {
                $longform->publish();
                echo PHP_EOL."The longform article has been written.".PHP_EOL.PHP_EOL;
            } catch (Exception $e) {
                echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
            }
        }

        if(str_contains($command, 'wiki')){
            // Define wiki page using the mock class
            $wiki = new MockWikiEvent();
            // read in settings file argument passed
            $wiki->setFile($secondArg);
            if (empty($wiki->getFile())) {
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
    } 
    else if(str_contains($command, 'fetch') || str_contains($command, 'delete') || str_contains($command, 'broadcast')){
        // Define utility
        $utility = new Utilities();
        $utility->setEventID($secondArg);

        // Call the appropriate utility
        try {
            $utility->run_utility($command);
            echo PHP_EOL."The utility run has finished.".PHP_EOL.PHP_EOL;
        } catch (Exception $e) {
            echo PHP_EOL.$e->getMessage().PHP_EOL.PHP_EOL;
        }
    } 
    else {
        throw new InvalidArgumentException(
            PHP_EOL.'That is not a valid command.'.PHP_EOL.PHP_EOL);
    }

<?php
/**
 * Publication Creator Script
 * 
 * This script creates and publishes Nostr events from AsciiDoc files.
 * It processes the input file, extracts metadata and content sections,
 * and creates the necessary publication and section events.
 * 
 * Usage: php createPublication.php <path-to-asciidoc-file>
 */

include_once __DIR__.'/vendor/autoload.php';
include_once 'src/PublicationEvent.php';
include_once 'src/Tag.php';
include_once 'src/SectionEvent.php';
include_once 'src/helperFunctions.php';

echo PHP_EOL;

// Define publication
$publication = new PublicationEvent();
// read in settings file argument passed
$publication->file = $argv[1];
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

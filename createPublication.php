<?php

include_once __DIR__.'/vendor/autoload.php';
include_once 'src/PublicationEvent.php';
include_once 'src/helperFunctions.php';

echo PHP_EOL;

// read in settings file argument passed
$settingsfile = $argv[1];
if (empty($settingsfile)) {
    throw new InvalidArgumentException('The settings file argument is missing.');
}

// check the contents of the file
$settings = yaml_parse_file($settingsfile);
if (empty($settings)) {
    throw new InvalidArgumentException('The settings file is empty.');
}
if (empty($settings['file'])) {
    throw new InvalidArgumentException('The markdown file path is missing.');
}
if (empty($settings['author'])) {
    throw new InvalidArgumentException('The author is missing.');
}
if (empty($settings['version'])) {
    throw new InvalidArgumentException('The version is missing.');
}
if ($settings['tag-type'] != ('e' || 'a')) {
    throw new InvalidArgumentException('The event type (e/a) is missing or wrong.');
}
if ($settings['auto-update'] != ('yes' || 'ask' || 'no')) {
    throw new InvalidArgumentException('The auto-update option is missing or wrong.');
}

// Define publication
$publication = new PublicationEvent();
$publication->setPublicationSettings($settings);

// Write publication into events
try {
    $publication->publish_publication();
    echo "The publication has been written.".PHP_EOL.PHP_EOL;
} catch (Exception $e) {
    echo $e->getMessage().PHP_EOL.PHP_EOL;
}
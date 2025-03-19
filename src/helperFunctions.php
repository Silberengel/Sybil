<?php

use swentel\nostr\Relay\Relay;
use swentel\nostr\Relay\RelaySet;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Event\Event;
use swentel\nostr\CommandResultInterface;

/**
 * Gets the original title and the author and constructs a d tag, which it returns.
 *
 * @param string $title
 * @param string $author # optional
 * @param string $version # optional
 * @return string $dTag
 */
function construct_d_tag($title, $author="unknown", $version="")
{
    // replace the spaces with dashes and ensure UTF-8
    $dTagAuthor = strval(preg_replace('/\s+/', '-', $author));
    $dTagTitle = strval(preg_replace('/\s+/', '-', $title));
    $dTagVersion = strval(preg_replace('/\s+/', '-', $version));

    if(strlen($version) === 0){
        $dTag = $dTagTitle . "-by-" . $dTagAuthor;
    } else {
        $dTag = $dTagTitle . "-by-" . $dTagAuthor . "-v-" . $dTagVersion;
    }

    /**
     * 
     * d tag is mandatory, for publisher the following rules:
     * consists of the title, author (if included), and version (if included)
     * all words in ASCII or URL-enocoding (publisher converts from UTF-8, where necessary)
     * words separated by a hyphen
     * words normalized to lowercase, and all punctuation and whitespace removed, except “.”
     * author preceeded with “by”
     * version preceeded with “v”
     * valid d-tags are therefore: 
     * title
     * title-by-author
     * title-by-author-v-version
     * Ex aesops-fables-by-aesop-v-5.0
     *  
     */

    $dTag = (strtolower(preg_replace("/(?![.-])\p{P}/u", "", mb_convert_encoding($dTag, 'UTF-8', mb_list_encodings()))));

    return $dTag;
}

/**
 * Signs the note, reads the relays out of relays.yml and uses them to prepare a note
 *
 * @param Event $note
 * @return CommandResultInterface $result
 */
function prepare_event_data($note): array
{
    // pull environment variable containing key that is allowed to AUTH to this relay
    // uses the same env name as NAK
    $privateKey = getenv('NOSTR_SECRET_KEY');

    // check to make sure that there is an nsec in the privateKey string.
    (str_starts_with($privateKey, 'nsec') === false) ? throw new InvalidArgumentException('Please place your nsec in the nostr-private.key file.') : $privateKey;

    $relaysFile = getcwd()."/user/relays.yml";
    $relaysRead = [];
    $relaysRead = file($relaysFile, FILE_IGNORE_NEW_LINES);
    $relaysRead = empty($relaysRead) ? ["wss://thecitadel.nostr1.com"] : $relaysRead;
    
    $signer = new Sign();
    $signer->signEvent($note, $privateKey);
  
    $eventMessage = new EventMessage($note);

    $relays = [];
    foreach ($relaysRead as &$relay) {
        $relays[] = new Relay(websocket: $relay);
    }
    (empty($relays)) ? ($relays = ["wss://thecitadel.nostr1.com"]) : $relays;

    $relaySet = new RelaySet();
    $relaySet->setRelays($relays);
    $relaySet->setMessage($eventMessage);

    try{
        $result = $relaySet->send();
    }catch(TypeError $e)
    {
        echo "Sending to relay did not work. Will be retried.";
        sleep(10);
        $result = $relaySet->send();
    }

    return $result;
}

/**
 * Prints the event data to a file.
 *
 * @param string $eventKind
 * @param string $eventID
 * @param string $dTag
 * @return void
 */
function print_event_data($eventKind, $eventID, $dTag)
{
    $fullpath = getcwd()."/eventsCreated.yml";
    $fp = fopen($fullpath, "a");
        fwrite($fp, "event ID: ".$eventID.PHP_EOL);
        fwrite($fp, "  event kind: ".$eventKind.PHP_EOL);
        fwrite($fp, "  d Tag: ".$dTag.PHP_EOL);
    fclose($fp);
}
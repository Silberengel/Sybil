<?php

use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
include_once 'helperFunctions.php';
include_once 'SectionEvent.php';

class BookEvent{

  // Properties

  public $bookSettings;
  public $bookDTag;
  public $bookTitle;
  public $bookAuthor;
  public $bookVersion;
  public $bookTagType;
  public $bookAutoUpdate;
  public $bookFileTags;
  public $sectionEvents = [];
  public $sectionDtags = [];

  // Methods

  function set_book_settings($bookSettings) {
    $this->bookSettings = $bookSettings;
  }

  function get_book_settings() {
    return $this->bookSettings;
  }

  function set_book_d_tag($bookDTag) {
    $this->bookDTag = $bookDTag;
  }

  function get_book_d_tag() {
    return $this->bookDTag;
  }

  function set_book_title($bookTitle) {
    $this->bookTitle = $bookTitle;
  }

  function get_book_title() {
    return $this->bookTitle;
  }

  function set_book_author($bookAuthor) {
    $this->bookAuthor = $bookAuthor;
  }

  function get_book_author() {
    return $this->bookAuthor;
  }

  function set_book_version($bookVersion) {
    $this->bookVersion = $bookVersion;
  }

  function get_book_version() {
    return $this->bookVersion;
  }

  function set_book_tagtype($bookTagType) {
    $this->bookTagType = $bookTagType;
  }

  function get_book_tagtype() {
    return $this->bookTagType;
  }

  function set_book_autoupdate($bookAutoUpdate) {
    $this->bookAutoUpdate = $bookAutoUpdate;
  }

  function get_book_autoupdate() {
    return $this->bookAutoUpdate;
  }

  function set_book_filetags($bookFileTags) {
    $this->bookFileTags = $bookFileTags;
  }

  function get_book_filetags(): mixed {
    return $this->bookFileTags;
  }

  function set_section_events($sectionEvents) {
    $this->sectionEvents[] = $sectionEvents;
  }

  function get_section_events() {
    return $this->sectionEvents;
  }

  function set_section_dTags($sectionDtags) {
    $this->sectionDtags[] = $sectionDtags;
  }

  function get_section_dTags() {
    return $this->sectionDtags;
  }

  /**
   * Create an index event and hang on the associated section events
   * Returns the eventID for the section event.
   *
   * @return void
   */
  function publish_book()
  {

      $markdown = file_get_contents($this->bookSettings['file']);
      if (!$markdown) {
          throw new InvalidArgumentException('The file could not be found or is empty.');
      }

      $this->set_book_author($this->bookSettings['author']);
      $this->set_book_version($this->bookSettings['version']);
      $this->set_book_tagtype($this->bookSettings['tag-type']);
      $this->set_book_autoupdate($this->bookSettings['auto-update']);
      $this->set_book_filetags($this->bookSettings['tags']);
      
      // replace headers above == with &s, so that they can be adding back in as discrete headings, after the split
      $markdown = str_replace('====== ', '&&&&&& ', $markdown);
      $markdown = str_replace('===== ', '&&&&& ', $markdown);
      $markdown = str_replace('==== ', '&&&& ', $markdown);
      $markdown = str_replace('=== ', '&&& ', $markdown);

      // break the file into metadata and sections
      $markdownFormatted = explode("== ", $markdown);

      // check if the file contains too few header levels
      (count($markdownFormatted) === 1) ? throw new InvalidArgumentException('This markdown file contains no headers or only one level of headers. Please ensure there are two levels and retry.') : $markdownFormatted;

      $bookTitle= array_shift($markdownFormatted);
      $this->set_book_title(trim(trim($bookTitle, "= ")));

      $title = $this->get_book_title();
      $author = $this->get_book_author();
      $version = $this->get_book_version();
      $dTag = construct_d_tag($title, $author, $version); 
      $this->set_book_d_tag($dTag);

      echo PHP_EOL;

      // write the 30041s from the == sections and add the eventID to the section array
      
      // replace headers above == with &s, so that they can be adding back in as discrete headings, after the split
      $markdownFormatted = str_replace('&&&&&& ', '[discrete]
      ====== ', $markdownFormatted);
      $markdownFormatted = str_replace('&&&&& ', '[discrete]
      ===== ', $markdownFormatted);
      $markdownFormatted = str_replace('&&&& ', '[discrete]
      ==== ', $markdownFormatted);
      $markdownFormatted = str_replace('&&& ', '[discrete]
      === ', $markdownFormatted);
      $markdownFormatted = str_replace('      ', '', $markdownFormatted);

      $sectionNum = 0;
      foreach ($markdownFormatted as &$section) {
        $sectionNum++;
        $sectionTitle = trim(strstr($section, "\n", true));
        $nextSection = new SectionEvent();
          $nextSection->set_section_author($this->bookAuthor);
          $nextSection->set_section_version($this->bookVersion);
                  $nextSection->set_section_title($sectionTitle);
          $nextSection->set_section_d_tag(construct_d_tag($this->get_book_title()."-".$nextSection->get_section_title()."-".$sectionNum, $nextSection->get_section_author(), $nextSection->get_section_version()));
          $nextSection->set_section_content(trim(trim(strval($section), $sectionTitle)));
          
          $sectionData = $nextSection->create_section();       
          $this->set_section_events($sectionData["eventID"]);
          $this->set_section_dTags($sectionData["dTag"]);

        }

      // write the 30040 and add the new 30041s
      if($this->get_book_tagtype() === 'e'){
        $this->create_book_with_e_tags();
      } else{
        $this->create_book_with_a_tags();
      }

      return;
  }

  /**
   * Create an index event and hang on the associated section events.
   * Returns the index as an event.
   *
   * @param BookEvent
   * 
   */
  function create_book_with_a_tags()
  {
    $kind = "30040";

    // get public hex key
    $keys = new Key();
    $privateBech32 = getenv(name: 'NOSTR_SECRET_KEY');
    $privateHex = $keys->convertToHex(key: $privateBech32);
    $publicHex = $keys->getPublicKey(private_hex: $privateHex);

    $tags = $this->build_tags();
    foreach ($this->get_section_events() as $eventIDs) {
      $dTag = array_shift($this->sectionDtags);
      array_push($tags, ['a', '30041:'.$publicHex.':'.$dTag, 'wss://thecitadel.nostr1.com', $eventIDs]); 
    }

    $note = new Event();
    $note->setKind($kind);
    $note->setTags($tags);
    $note->setContent("");

    $result = prepare_event_data($note);
    $this->record_result($kind, $note, $type='a');
    
  }

  function create_book_with_e_tags()
  {
    $kind = "30040";

    $tags = $this->build_tags();
    foreach ($this->get_section_events() as &$etags) {
      $tags[] = ['e', $etags];
    }

    $note = new Event();
    $note->setKind($kind);
    $note->setTags($tags);
    $note->setContent("");

    $result = prepare_event_data($note);
    $this->record_result($kind, $note, $type='e');
  }

  function build_tags(): array 
  {
    $tags = $this->get_book_filetags();
    $tags[] = ['d', $this->get_book_d_tag()];
    $tags[] = ['title', $this->get_book_title()];
    $tags[] = ['author', $this->get_book_author()];
    $tags[] = ['version', $this->get_book_version()];
    $tags[] = ["m", "application/json"];
    $tags[] = ["M", "meta-data/index/replaceable"];

    return $tags;
  }

  function record_result($kind, $note, $type): void
  {
    // issue the eventID, pause to prevent the relay from balking, and retry on fail
    $i = 0;
    do {
      $eventID = $note->getId();
      $i++;
      sleep(5);
    } while (($i <= 10) && empty($eventID));

    if (empty($eventID)) {
              throw new InvalidArgumentException('The book eventID was not created');
          }
    
    echo "Published ".$kind." event with ".$type." tags and ID ".$eventID.PHP_EOL.PHP_EOL;
    print_event_data($kind, $eventID, $this->get_book_d_tag());
    
    // print a njump hyperlink to the 30040
    print "https://njump.me/".$eventID.PHP_EOL;
  }
}
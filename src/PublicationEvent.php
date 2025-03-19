<?php

use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
include_once 'helperFunctions.php';
include_once 'SectionEvent.php';

class PublicationEvent{

  // Properties

  public $publicationSettings;
  public $publicationDTag;
  public $publicationTitle;
  public $publicationAuthor;
  public $publicationVersion;
  public $publicationTagType;
  public $publicationAutoUpdate;
  public $publicationFileTags;
  public $sectionEvents = [];
  public $sectionDtags = [];

  // Methods

  function set_publication_settings($publicationSettings) {
    $this->publicationSettings = $publicationSettings;
  }

  function get_publication_settings() {
    return $this->publicationSettings;
  }

  function set_publication_d_tag($publicationDTag) {
    $this->publicationDTag = $publicationDTag;
  }

  function get_publication_d_tag() {
    return $this->publicationDTag;
  }

  function set_publication_title($publicationTitle) {
    $this->publicationTitle = $publicationTitle;
  }

  function get_publication_title() {
    return $this->publicationTitle;
  }

  function set_publication_author($publicationAuthor) {
    $this->publicationAuthor = $publicationAuthor;
  }

  function get_publication_author() {
    return $this->publicationAuthor;
  }

  function set_publication_version($publicationVersion) {
    $this->publicationVersion = $publicationVersion;
  }

  function get_publication_version() {
    return $this->publicationVersion;
  }

  function set_publication_tagtype($publicationTagType) {
    $this->publicationTagType = $publicationTagType;
  }

  function get_publication_tagtype() {
    return $this->publicationTagType;
  }

  function set_publication_autoupdate($publicationAutoUpdate) {
    $this->publicationAutoUpdate = $publicationAutoUpdate;
  }

  function get_publication_autoupdate() {
    return $this->publicationAutoUpdate;
  }

  function set_publication_filetags($publicationFileTags) {
    $this->publicationFileTags = $publicationFileTags;
  }

  function get_publication_filetags(): mixed {
    return $this->publicationFileTags;
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
  function publish_publication()
  {

      $markdown = file_get_contents($this->publicationSettings['file']);
      if (!$markdown) {
          throw new InvalidArgumentException('The file could not be found or is empty.');
      }

      $this->set_publication_author($this->publicationSettings['author']);
      $this->set_publication_version($this->publicationSettings['version']);
      $this->set_publication_tagtype($this->publicationSettings['tag-type']);
      $this->set_publication_autoupdate($this->publicationSettings['auto-update']);
      $this->set_publication_filetags($this->publicationSettings['tags']);
      
      // check if the file contains too many header levels
      (stripos($markdown,'======= ') !== false) ? throw new InvalidArgumentException('This markdown file contains too many header levels. Please correct down to maximum six = levels and retry.') : $markdown;

      // replace headers above == with &s, so that they can be adding back in as discrete headings, after the split
      $markdown = str_replace('====== ', '&&&&&& ', $markdown);
      $markdown = str_replace('===== ', '&&&&& ', $markdown);
      $markdown = str_replace('==== ', '&&&& ', $markdown);
      $markdown = str_replace('=== ', '&&& ', $markdown);

      // break the file into metadata and sections
      $markdownFormatted = explode("== ", $markdown);

      // check if the file contains too few header levels
      (count($markdownFormatted) === 1) ? throw new InvalidArgumentException('This markdown file contains no headers or only one level of headers. Please ensure there are two levels and retry.') : $markdownFormatted;

      // set aside publication title
      $firstSection = explode(PHP_EOL, $markdownFormatted[0], 2);
      $this->set_publication_title(trim(trim($firstSection[0], "= ")));
      
      // check if preamble exists and make it the first section
      $markdownFormatted[0] = trim($firstSection[1]);

      if(!empty($markdownFormatted[0])){
        $markdownFormatted[0] = 'Preamble'.PHP_EOL.PHP_EOL.$markdownFormatted[0];
      }else unset($markdownFormatted[0]);

      $title = $this->get_publication_title();
      $author = $this->get_publication_author();
      $version = $this->get_publication_version();
      $dTag = construct_d_tag($title, $author, $version); 
      $this->set_publication_d_tag($dTag);

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
          $nextSection->set_section_author($this->publicationAuthor);
          $nextSection->set_section_version($this->publicationVersion);
                  $nextSection->set_section_title($sectionTitle);
          $nextSection->set_section_d_tag(construct_d_tag($this->get_publication_title()."-".$nextSection->get_section_title()."-".$sectionNum, $nextSection->get_section_author(), $nextSection->get_section_version()));
          $nextSection->set_section_content(trim(trim(strval($section), $sectionTitle)));
          
          $sectionData = $nextSection->create_section();       
          $this->set_section_events($sectionData["eventID"]);
          $this->set_section_dTags($sectionData["dTag"]);

        }

      // write the 30040 and add the new 30041s
      if($this->get_publication_tagtype() === 'e'){
        $this->create_publication_with_e_tags();
      } else{
        $this->create_publication_with_a_tags();
      }

      return;
  }

  /**
   * Create an index event and hang on the associated section events.
   * Returns the index as an event.
   *
   * @param PublicationEvent
   * 
   */
  function create_publication_with_a_tags()
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

  function create_publication_with_e_tags()
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
    $tags = $this->get_publication_filetags();
    $tags[] = ['d', $this->get_publication_d_tag()];
    $tags[] = ['title', $this->get_publication_title()];
    $tags[] = ['author', $this->get_publication_author()];
    $tags[] = ['version', $this->get_publication_version()];
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
              throw new InvalidArgumentException('The publication eventID was not created');
          }
    
    echo "Published ".$kind." event with ".$type." tags and ID ".$eventID.PHP_EOL.PHP_EOL;
    print_event_data($kind, $eventID, $this->get_publication_d_tag());
    
    // print a njump hyperlink to the 30040
    print "https://njump.me/".$eventID.PHP_EOL;
  }
}
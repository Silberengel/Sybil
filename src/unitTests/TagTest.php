<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tag class
 */
final class TagTest extends TestCase
{
    protected function setUp(): void
    {
        // Include the Tag class file
        include_once dirname(__DIR__) . '/Tag.php';
    }

    /**
     * Test createSectionTags with basic parameters
     */
    public function testCreateSectionTagsBasic(): void
    {
        $dTag = 'test-d-tag';
        $title = 'Test Title';
        $author = 'Test Author';
        
        $result = Tag::createSectionTags($dTag, $title, $author);
        
        $this->assertCount(5, $result);
        $this->assertEquals(['d', $dTag], $result[0]);
        $this->assertEquals(['title', $title], $result[1]);
        $this->assertEquals(['author', $author], $result[2]);
        $this->assertEquals(['m', 'text/asciidoc'], $result[3]);
        $this->assertEquals(['M', 'article/publication-content/replaceable'], $result[4]);
    }

    /**
     * Test createSectionTags with optional tags
     */
    public function testCreateSectionTagsWithOptionalTags(): void
    {
        $dTag = 'test-d-tag';
        $title = 'Test Title';
        $author = 'Test Author';
        $optionalTags = [
            ['tag1', 'value1'],
            ['tag2', 'value2']
        ];
        
        $result = Tag::createSectionTags($dTag, $title, $author, $optionalTags);
        
        $this->assertCount(7, $result);
        $this->assertEquals(['d', $dTag], $result[0]);
        $this->assertEquals(['title', $title], $result[1]);
        $this->assertEquals(['author', $author], $result[2]);
        $this->assertEquals(['m', 'text/asciidoc'], $result[3]);
        $this->assertEquals(['M', 'article/publication-content/replaceable'], $result[4]);
        $this->assertEquals(['tag1', 'value1'], $result[5]);
        $this->assertEquals(['tag2', 'value2'], $result[6]);
    }

    /**
     * Test createSectionTags with empty optional tags
     */
    public function testCreateSectionTagsWithEmptyOptionalTags(): void
    {
        $dTag = 'test-d-tag';
        $title = 'Test Title';
        $author = 'Test Author';
        $optionalTags = [];
        
        $result = Tag::createSectionTags($dTag, $title, $author, $optionalTags);
        
        $this->assertCount(5, $result);
        $this->assertEquals(['d', $dTag], $result[0]);
        $this->assertEquals(['title', $title], $result[1]);
        $this->assertEquals(['author', $author], $result[2]);
        $this->assertEquals(['m', 'text/asciidoc'], $result[3]);
        $this->assertEquals(['M', 'article/publication-content/replaceable'], $result[4]);
    }

    /**
     * Test createPublicationTags with basic parameters
     */
    public function testCreatePublicationTagsBasic(): void
    {
        $dTag = 'test-d-tag';
        $title = 'Test Title';
        $author = 'Test Author';
        $version = '1.0';
        
        $result = Tag::createPublicationTags($dTag, $title, $author, $version);
        
        $this->assertCount(6, $result);
        $this->assertEquals(['d', $dTag], $result[0]);
        $this->assertEquals(['title', $title], $result[1]);
        $this->assertEquals(['author', $author], $result[2]);
        $this->assertEquals(['version', $version], $result[3]);
        $this->assertEquals(['m', 'application/json'], $result[4]);
        $this->assertEquals(['M', 'meta-data/index/replaceable'], $result[5]);
    }

    /**
     * Test createPublicationTags with event tags
     */
    public function testCreatePublicationTagsWithEventTags(): void
    {
        $dTag = 'test-d-tag';
        $title = 'Test Title';
        $author = 'Test Author';
        $version = '1.0';
        $eventTags = [
            ['tag1', 'value1'],
            ['tag2', 'value2']
        ];
        
        $result = Tag::createPublicationTags($dTag, $title, $author, $version, $eventTags);
        
        $this->assertCount(8, $result);
        $this->assertEquals(['d', $dTag], $result[0]);
        $this->assertEquals(['title', $title], $result[1]);
        $this->assertEquals(['author', $author], $result[2]);
        $this->assertEquals(['version', $version], $result[3]);
        $this->assertEquals(['m', 'application/json'], $result[4]);
        $this->assertEquals(['M', 'meta-data/index/replaceable'], $result[5]);
        $this->assertEquals(['tag1', 'value1'], $result[6]);
        $this->assertEquals(['tag2', 'value2'], $result[7]);
    }

    /**
     * Test createPublicationTags with empty event tags
     */
    public function testCreatePublicationTagsWithEmptyEventTags(): void
    {
        $dTag = 'test-d-tag';
        $title = 'Test Title';
        $author = 'Test Author';
        $version = '1.0';
        $eventTags = [];
        
        $result = Tag::createPublicationTags($dTag, $title, $author, $version, $eventTags);
        
        $this->assertCount(6, $result);
        $this->assertEquals(['d', $dTag], $result[0]);
        $this->assertEquals(['title', $title], $result[1]);
        $this->assertEquals(['author', $author], $result[2]);
        $this->assertEquals(['version', $version], $result[3]);
        $this->assertEquals(['m', 'application/json'], $result[4]);
        $this->assertEquals(['M', 'meta-data/index/replaceable'], $result[5]);
    }

    /**
     * Test addATags with single section event
     */
    public function testAddATagsWithSingleSectionEvent(): void
    {
        $sectionEvents = ['event-id-1'];
        $sectionDtags = ['d-tag-1'];
        $sectionEventKind = '30041';
        $publicHex = 'public-hex';
        $defaultRelay = 'wss://relay.com';
        
        $result = Tag::addATags($sectionEvents, $sectionDtags, $sectionEventKind, $publicHex, $defaultRelay);
        
        $this->assertCount(1, $result);
        $this->assertEquals(['a', '30041:public-hex:d-tag-1', 'wss://relay.com', 'event-id-1'], $result[0]);
    }

    /**
     * Test addATags with multiple section events
     */
    public function testAddATagsWithMultipleSectionEvents(): void
    {
        $sectionEvents = ['event-id-1', 'event-id-2', 'event-id-3'];
        $sectionDtags = ['d-tag-1', 'd-tag-2', 'd-tag-3'];
        $sectionEventKind = '30041';
        $publicHex = 'public-hex';
        $defaultRelay = 'wss://relay.com';
        
        $result = Tag::addATags($sectionEvents, $sectionDtags, $sectionEventKind, $publicHex, $defaultRelay);
        
        $this->assertCount(3, $result);
        $this->assertEquals(['a', '30041:public-hex:d-tag-1', 'wss://relay.com', 'event-id-1'], $result[0]);
        $this->assertEquals(['a', '30041:public-hex:d-tag-2', 'wss://relay.com', 'event-id-2'], $result[1]);
        $this->assertEquals(['a', '30041:public-hex:d-tag-3', 'wss://relay.com', 'event-id-3'], $result[2]);
    }

    /**
     * Test addATags with empty section events
     */
    public function testAddATagsWithEmptySectionEvents(): void
    {
        $sectionEvents = [];
        $sectionDtags = [];
        $sectionEventKind = '30041';
        $publicHex = 'public-hex';
        $defaultRelay = 'wss://relay.com';
        
        $result = Tag::addATags($sectionEvents, $sectionDtags, $sectionEventKind, $publicHex, $defaultRelay);
        
        $this->assertEmpty($result);
    }

    /**
     * Test addETags with single section event
     */
    public function testAddETagsWithSingleSectionEvent(): void
    {
        $sectionEvents = ['event-id-1'];
        
        $result = Tag::addETags($sectionEvents);
        
        $this->assertCount(1, $result);
        $this->assertEquals(['e', 'event-id-1'], $result[0]);
    }

    /**
     * Test addETags with multiple section events
     */
    public function testAddETagsWithMultipleSectionEvents(): void
    {
        $sectionEvents = ['event-id-1', 'event-id-2', 'event-id-3'];
        
        $result = Tag::addETags($sectionEvents);
        
        $this->assertCount(3, $result);
        $this->assertEquals(['e', 'event-id-1'], $result[0]);
        $this->assertEquals(['e', 'event-id-2'], $result[1]);
        $this->assertEquals(['e', 'event-id-3'], $result[2]);
    }

    /**
     * Test addETags with empty section events
     */
    public function testAddETagsWithEmptySectionEvents(): void
    {
        $sectionEvents = [];
        
        $result = Tag::addETags($sectionEvents);
        
        $this->assertEmpty($result);
    }
}

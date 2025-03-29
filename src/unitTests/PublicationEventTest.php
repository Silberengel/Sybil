<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;

/**
 * Unit tests for PublicationEvent class
 */
final class PublicationEventTest extends TestCase
{
    protected function setUp(): void
    {
        // Include necessary files
        include_once dirname(__DIR__) . '/Tag.php';
        include_once dirname(__DIR__) . '/SectionEvent.php';
        include_once dirname(__DIR__) . '/PublicationEvent.php';
    }

    /**
     * Test constructor with empty data
     */
    public function testConstructorWithEmptyData(): void
    {
        $publicationEvent = new PublicationEvent();
        
        $this->assertEquals('', $publicationEvent->file);
        $this->assertEquals('', $publicationEvent->dTag);
        $this->assertEquals('', $publicationEvent->title);
        $this->assertEquals('', $publicationEvent->author);
        $this->assertEquals('', $publicationEvent->version);
        $this->assertEquals('', $publicationEvent->tagType);
        $this->assertEquals('', $publicationEvent->autoUpdate);
        $this->assertEmpty($publicationEvent->optionaltags);
        $this->assertEmpty($publicationEvent->sectionEvents);
        $this->assertEmpty($publicationEvent->sectionDtags);
    }

    /**
     * Test constructor with data
     */
    public function testConstructorWithData(): void
    {
        $data = [
            'title' => 'Test Title',
            'author' => 'Test Author',
            'version' => '1.0',
            'dTag' => 'test-d-tag'
        ];
        
        $publicationEvent = new PublicationEvent($data);
        
        $this->assertEquals('', $publicationEvent->file);
        $this->assertEquals('test-d-tag', $publicationEvent->dTag);
        $this->assertEquals('Test Title', $publicationEvent->title);
        $this->assertEquals('Test Author', $publicationEvent->author);
        $this->assertEquals('1.0', $publicationEvent->version);
        $this->assertEquals('', $publicationEvent->tagType);
        $this->assertEquals('', $publicationEvent->autoUpdate);
        $this->assertEmpty($publicationEvent->optionaltags);
        $this->assertEmpty($publicationEvent->sectionEvents);
        $this->assertEmpty($publicationEvent->sectionDtags);
    }

    /**
     * Test constructor with partial data
     */
    public function testConstructorWithPartialData(): void
    {
        $data = [
            'title' => 'Test Title',
            'author' => 'Test Author'
        ];
        
        $publicationEvent = new PublicationEvent($data);
        
        $this->assertEquals('', $publicationEvent->file);
        $this->assertEquals('', $publicationEvent->dTag);
        $this->assertEquals('Test Title', $publicationEvent->title);
        $this->assertEquals('Test Author', $publicationEvent->author);
        $this->assertEquals('', $publicationEvent->version);
        $this->assertEquals('', $publicationEvent->tagType);
        $this->assertEquals('', $publicationEvent->autoUpdate);
        $this->assertEmpty($publicationEvent->optionaltags);
        $this->assertEmpty($publicationEvent->sectionEvents);
        $this->assertEmpty($publicationEvent->sectionDtags);
    }

    /**
     * Test loadMarkupFile method with valid file
     * 
     * This test uses reflection to access the private method
     */
    public function testLoadMarkupFileWithValidFile(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_markup_');
        file_put_contents($tempFile, 'Test markup content');
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        $publicationEvent->file = $tempFile;
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'loadMarkupFile');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent);
        
        // Verify the result
        $this->assertEquals('Test markup content', $result);
        
        // Clean up
        unlink($tempFile);
    }

    /**
     * Test loadMarkupFile method with non-existent file
     * 
     * This test uses reflection to access the private method
     */
    public function testLoadMarkupFileWithNonExistentFile(): void
    {
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        $publicationEvent->file = '/non/existent/file.adoc';
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'loadMarkupFile');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file could not be found or is empty.');
        
        // Call the method
        $reflectionMethod->invoke($publicationEvent);
    }

    /**
     * Test loadMarkupFile method with too many header levels
     * 
     * This test uses reflection to access the private method
     */
    public function testLoadMarkupFileWithTooManyHeaderLevels(): void
    {
        // Create a temporary file with too many header levels
        $tempFile = tempnam(sys_get_temp_dir(), 'test_markup_');
        file_put_contents($tempFile, 'Some content\n======= Too many levels');
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        $publicationEvent->file = $tempFile;
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'loadMarkupFile');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This markup file contains too many header levels.');
        
        try {
            // Call the method
            $reflectionMethod->invoke($publicationEvent);
        } finally {
            // Clean up
            unlink($tempFile);
        }
    }

    /**
     * Test preprocessMarkup method with valid markup
     * 
     * This test uses reflection to access the private method
     */
    public function testPreprocessMarkupWithValidMarkup(): void
    {
        $markup = "Title\n\n== Section 1\nContent 1\n\n== Section 2\nContent 2";
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent, $markup);
        
        // Verify the result
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals("Title\n\n", $result[0]);
        $this->assertEquals("Section 1\nContent 1\n\n", $result[1]);
        $this->assertEquals("Section 2\nContent 2", $result[2]);
    }

    /**
     * Test preprocessMarkup method with no sections
     * 
     * This test uses reflection to access the private method
     */
    public function testPreprocessMarkupWithNoSections(): void
    {
        $markup = "Title\n\nContent without sections";
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This markup file contains no headers or only one level of headers.');
        
        // Call the method
        $reflectionMethod->invoke($publicationEvent, $markup);
    }

    /**
     * Test replaceHeadersForProcessing method
     * 
     * This test uses reflection to access the private method
     */
    public function testReplaceHeadersForProcessing(): void
    {
        $markup = "=== Level 3\n==== Level 4\n===== Level 5\n====== Level 6";
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'replaceHeadersForProcessing');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent, $markup);
        
        // Verify the result
        $this->assertEquals("&&& Level 3\n&&&& Level 4\n&&&&& Level 5\n&&&&&& Level 6", $result);
    }

    /**
     * Test restoreHeaderMarkers method
     * 
     * This test uses reflection to access the private method
     */
    public function testRestoreHeaderMarkers(): void
    {
        $markupFormatted = [
            "Title\n\n&&& Level 3\n&&&& Level 4",
            "Section\n&&&&& Level 5\n&&&&&& Level 6"
        ];
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'restoreHeaderMarkers');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent, $markupFormatted);
        
        // Verify the result
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals("Title\n\n[discrete]\n=== Level 3\n[discrete]\n==== Level 4", $result[0]);
        $this->assertEquals("Section\n[discrete]\n===== Level 5\n[discrete]\n====== Level 6", $result[1]);
    }

    /**
     * Test buildPublicationEvent method with e-tags
     * 
     * This test uses reflection to access the private method
     */
    public function testBuildPublicationEventWithETags(): void
    {
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        $publicationEvent->dTag = 'test-d-tag';
        $publicationEvent->title = 'Test Title';
        $publicationEvent->author = 'Test Author';
        $publicationEvent->version = '1.0';
        $publicationEvent->sectionEvents = ['event-id-1', 'event-id-2'];
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'buildPublicationEvent');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent, 'e');
        
        // Verify the result
        $this->assertInstanceOf(Event::class, $result);
        
        // Use reflection to access protected properties
        $reflectionClass = new ReflectionClass(Event::class);
        
        $kindProperty = $reflectionClass->getProperty('kind');
        $kindProperty->setAccessible(true);
        $this->assertEquals(PublicationEvent::EVENT_KIND, $kindProperty->getValue($result));
        
        $contentProperty = $reflectionClass->getProperty('content');
        $contentProperty->setAccessible(true);
        $this->assertEquals('', $contentProperty->getValue($result));
        
        // Verify the tags
        $tagsProperty = $reflectionClass->getProperty('tags');
        $tagsProperty->setAccessible(true);
        $tags = $tagsProperty->getValue($result);
        
        $this->assertIsArray($tags);
        $this->assertGreaterThanOrEqual(8, count($tags));
        $this->assertEquals(['d', 'test-d-tag'], $tags[0]);
        $this->assertEquals(['title', 'Test Title'], $tags[1]);
        $this->assertEquals(['author', 'Test Author'], $tags[2]);
        $this->assertEquals(['version', '1.0'], $tags[3]);
        $this->assertEquals(['e', 'event-id-1'], $tags[6]);
        $this->assertEquals(['e', 'event-id-2'], $tags[7]);
    }

    /**
     * Test buildPublicationEvent method with a-tags
     * 
     * This test uses reflection to access the private method
     */
    public function testBuildPublicationEventWithATags(): void
    {
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        $publicationEvent->dTag = 'test-d-tag';
        $publicationEvent->title = 'Test Title';
        $publicationEvent->author = 'Test Author';
        $publicationEvent->version = '1.0';
        $publicationEvent->sectionEvents = ['event-id-1', 'event-id-2'];
        $publicationEvent->sectionDtags = ['d-tag-1', 'd-tag-2'];
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'buildPublicationEvent');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent, 'a', 'public-hex');
        
        // Verify the result
        $this->assertInstanceOf(Event::class, $result);
        
        // Use reflection to access protected properties
        $reflectionClass = new ReflectionClass(Event::class);
        
        $kindProperty = $reflectionClass->getProperty('kind');
        $kindProperty->setAccessible(true);
        $this->assertEquals(PublicationEvent::EVENT_KIND, $kindProperty->getValue($result));
        
        $contentProperty = $reflectionClass->getProperty('content');
        $contentProperty->setAccessible(true);
        $this->assertEquals('', $contentProperty->getValue($result));
        
        // Verify the tags
        $tagsProperty = $reflectionClass->getProperty('tags');
        $tagsProperty->setAccessible(true);
        $tags = $tagsProperty->getValue($result);
        
        $this->assertIsArray($tags);
        $this->assertGreaterThanOrEqual(8, count($tags));
        $this->assertEquals(['d', 'test-d-tag'], $tags[0]);
        $this->assertEquals(['title', 'Test Title'], $tags[1]);
        $this->assertEquals(['author', 'Test Author'], $tags[2]);
        $this->assertEquals(['version', '1.0'], $tags[3]);
        
        // Check a-tags
        $this->assertEquals('a', $tags[6][0]);
        $this->assertEquals('30041:public-hex:d-tag-1', $tags[6][1]);
        $this->assertEquals('wss://thecitadel.nostr1.com', $tags[6][2]);
        $this->assertEquals('event-id-1', $tags[6][3]);
        
        $this->assertEquals('a', $tags[7][0]);
        $this->assertEquals('30041:public-hex:d-tag-2', $tags[7][1]);
        $this->assertEquals('wss://thecitadel.nostr1.com', $tags[7][2]);
        $this->assertEquals('event-id-2', $tags[7][3]);
    }

    /**
     * Test getEventIdWithRetry method with successful retrieval
     * 
     * This test uses reflection to access the private method
     */
    public function testGetEventIdWithRetrySuccess(): void
    {
        // Create a mock for the Event class
        $eventMock = $this->createMock(Event::class);
        $eventMock->method('getId')->willReturn('mock-event-id');
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'getEventIdWithRetry');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($publicationEvent, $eventMock);
        
        // Verify the result
        $this->assertEquals('mock-event-id', $result);
    }

    /**
     * Test getEventIdWithRetry method with retry and eventual success
     * 
     * This test uses reflection to access the private method
     */
    public function testGetEventIdWithRetryEventualSuccess(): void
    {
        // Create a mock for the Event class
        $eventMock = $this->createMock(Event::class);
        
        // Configure the mock to return empty string first, then a valid ID
        $eventMock->expects($this->exactly(2))
            ->method('getId')
            ->willReturnOnConsecutiveCalls('', 'mock-event-id');
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'getEventIdWithRetry');
        $reflectionMethod->setAccessible(true);
        
        // Call the method with a short delay and only 1 retry
        $result = $reflectionMethod->invoke($publicationEvent, $eventMock, 1, 1);
        
        // Verify the result
        $this->assertEquals('mock-event-id', $result);
    }

    /**
     * Test getEventIdWithRetry method with failure
     * 
     * This test uses reflection to access the private method
     */
    public function testGetEventIdWithRetryFailure(): void
    {
        // Create a mock for the Event class
        $eventMock = $this->createMock(Event::class);
        $eventMock->method('getId')->willReturn('');
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        
        // Use reflection to access the private method
        $reflectionMethod = new ReflectionMethod(PublicationEvent::class, 'getEventIdWithRetry');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The publication eventID was not created');
        
        // Call the method with a short delay and only 1 retry
        $reflectionMethod->invoke($publicationEvent, $eventMock, 1, 1);
    }

    /**
     * Test recordResult method
     * 
     * This test uses a mock for the Event class and captures output
     */
    public function testRecordResult(): void
    {
        // Create a mock for the Event class
        $eventMock = $this->createMock(Event::class);
        $eventMock->method('getId')->willReturn('mock-event-id');
        
        // Create a PublicationEvent instance
        $publicationEvent = new PublicationEvent();
        $publicationEvent->dTag = 'test-d-tag';
        
        // We'll use the actual print_event_data function
        // which is already defined in HelperFunctions.php
        
        // Start output buffering
        ob_start();
        
        // Call the method
        $publicationEvent->recordResult('30040', $eventMock, 'e');
        
        // Get the output
        $output = ob_get_clean();
        
        // Verify the output
        $this->assertStringContainsString('Published 30040 event with e tags and ID mock-event-id', $output);
        $this->assertStringContainsString('https://njump.me/mock-event-id', $output);
    }

    /**
     * Test createWithETags method
     * 
     * This test skips the actual network calls
     */
    public function testCreateWithETags(): void
    {
        // Skip this test as it requires mocking private methods
        $this->markTestSkipped('This test requires mocking private methods which is not possible.');
    }

    /**
     * Test createWithATags method
     * 
     * This test skips the actual network calls
     */
    public function testCreateWithATags(): void
    {
        // Skip this test as it requires mocking private methods
        $this->markTestSkipped('This test requires mocking private methods which is not possible.');
    }

    /**
     * Helper method to set a static property on a class
     */
    private function setStaticProperty($class, $property, $value): void
    {
        $reflection = new ReflectionClass($class);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue(null, $value);
    }
}

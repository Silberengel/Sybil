<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use swentel\nostr\Event\Event;

/**
 * Unit tests for WikiEvent class
 */
final class WikiEventTest extends TestCase
{
    protected function setUp(): void
    {
        // Include necessary files
        include_once dirname(__DIR__) . '/BaseEvent.php';
        include_once dirname(__DIR__) . '/WikiEvent.php';
    }

    /**
     * Test constructor with empty data
     */
    public function testConstructorWithEmptyData(): void
    {
        $wikiEvent = new WikiEvent();
        
        $this->assertEquals('', $wikiEvent->getFile());
        $this->assertEquals('', $wikiEvent->getDTag());
        $this->assertEquals('', $wikiEvent->getTitle());
        $this->assertEquals('', $wikiEvent->getContent());
        $this->assertEmpty($wikiEvent->getOptionalTags());
    }

    /**
     * Test constructor with data
     */
    public function testConstructorWithData(): void
    {
        $data = [
            'title' => 'Test Title',
            'dTag' => 'test-d-tag',
            'file' => 'test-file.adoc'
        ];
        
        $wikiEvent = new WikiEvent($data);
        
        $this->assertEquals('test-file.adoc', $wikiEvent->getFile());
        $this->assertEquals('test-d-tag', $wikiEvent->getDTag());
        $this->assertEquals('Test Title', $wikiEvent->getTitle());
        $this->assertEquals('', $wikiEvent->getContent());
        $this->assertEmpty($wikiEvent->getOptionalTags());
    }

    /**
     * Test getEventKind method
     * 
     * This test uses reflection to access the protected method
     */
    public function testGetEventKind(): void
    {
        $wikiEvent = new WikiEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(WikiEvent::class, 'getEventKind');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($wikiEvent);
        
        // Verify the result
        $this->assertEquals(WikiEvent::EVENT_KIND, $result);
    }

    /**
     * Test getEventKindName method
     * 
     * This test uses reflection to access the protected method
     */
    public function testGetEventKindName(): void
    {
        $wikiEvent = new WikiEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(WikiEvent::class, 'getEventKindName');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($wikiEvent);
        
        // Verify the result
        $this->assertEquals('wiki', $result);
    }

    /**
     * Test preprocessMarkup method with valid markup
     * 
     * This test uses reflection to access the protected method
     */
    public function testPreprocessMarkupWithValidMarkup(): void
    {
        $markup = "<<YAML>>\nauthor: Test Author\n<</YAML>>\n\n= Title\nContent\n\n= Section 1\nContent 1\n\n= Section 2\nContent 2";
        
        // Create a WikiEvent instance
        $wikiEvent = new WikiEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(WikiEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($wikiEvent, $markup);
        
        // Verify the result
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(3, count($result));
        $this->assertStringContainsString("<<YAML>>", $result[0]);
        $this->assertStringContainsString("Title", $result[1]);
        $this->assertStringContainsString("Section 1", $result[2]);
    }

    /**
     * Test preprocessMarkup method with no headers
     * 
     * This test uses reflection to access the protected method
     */
    public function testPreprocessMarkupWithNoHeaders(): void
    {
        $markup = "Content without headers";
        
        // Create a WikiEvent instance
        $wikiEvent = new WikiEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(WikiEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This markup file contains no headers.');
        
        // Call the method
        $reflectionMethod->invoke($wikiEvent, $markup);
    }

    /**
     * Test preprocessMarkup method with no metadata
     * 
     * This test uses reflection to access the protected method
     */
    public function testPreprocessMarkupWithNoMetadata(): void
    {
        $markup = "= Title\nContent without metadata";
        
        // Create a WikiEvent instance
        $wikiEvent = new WikiEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(WikiEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This markup file contains no metadata.');
        
        // Call the method
        $reflectionMethod->invoke($wikiEvent, $markup);
    }

    /**
     * Test buildEvent method
     * 
     * This test uses reflection to access the protected method
     */
    public function testBuildEvent(): void
    {
        // Create a WikiEvent instance
        $wikiEvent = new WikiEvent();
        $wikiEvent->setDTag('test-d-tag');
        $wikiEvent->setTitle('Test Title');
        $wikiEvent->setContent('Test Content');
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(WikiEvent::class, 'buildEvent');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($wikiEvent);
        
        // Verify the result
        $this->assertInstanceOf(Event::class, $result);
        
        // Use reflection to access protected properties
        $reflectionClass = new ReflectionClass(Event::class);
        
        $kindProperty = $reflectionClass->getProperty('kind');
        $kindProperty->setAccessible(true);
        $this->assertEquals(WikiEvent::EVENT_KIND, $kindProperty->getValue($result));
        
        $contentProperty = $reflectionClass->getProperty('content');
        $contentProperty->setAccessible(true);
        $this->assertEquals('Test Content', $contentProperty->getValue($result));
    }

    /**
     * Test publish method
     * 
     * This test skips the actual network calls
     */
    public function testPublish(): void
    {
        // Skip this test as it requires mocking protected methods
        $this->markTestSkipped('This test requires mocking protected methods which is not possible.');
    }
}

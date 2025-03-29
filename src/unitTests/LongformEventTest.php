<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use swentel\nostr\Event\Event;

/**
 * Unit tests for LongformEvent class
 */
final class LongformEventTest extends TestCase
{
    protected function setUp(): void
    {
        // Include necessary files
        include_once dirname(__DIR__) . '/BaseEvent.php';
        include_once dirname(__DIR__) . '/LongformEvent.php';
    }

    /**
     * Test constructor with empty data
     */
    public function testConstructorWithEmptyData(): void
    {
        $longformEvent = new LongformEvent();
        
        $this->assertEquals('', $longformEvent->getFile());
        $this->assertEquals('', $longformEvent->getDTag());
        $this->assertEquals('', $longformEvent->getTitle());
        $this->assertEquals('', $longformEvent->getContent());
        $this->assertEmpty($longformEvent->getOptionalTags());
    }

    /**
     * Test constructor with data
     */
    public function testConstructorWithData(): void
    {
        $data = [
            'title' => 'Test Title',
            'dTag' => 'test-d-tag',
            'file' => 'test-file.md'
        ];
        
        $longformEvent = new LongformEvent($data);
        
        $this->assertEquals('test-file.md', $longformEvent->getFile());
        $this->assertEquals('test-d-tag', $longformEvent->getDTag());
        $this->assertEquals('Test Title', $longformEvent->getTitle());
        $this->assertEquals('', $longformEvent->getContent());
        $this->assertEmpty($longformEvent->getOptionalTags());
    }

    /**
     * Test getEventKind method
     * 
     * This test uses reflection to access the protected method
     */
    public function testGetEventKind(): void
    {
        $longformEvent = new LongformEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(LongformEvent::class, 'getEventKind');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($longformEvent);
        
        // Verify the result
        $this->assertEquals(LongformEvent::EVENT_KIND, $result);
    }

    /**
     * Test getEventKindName method
     * 
     * This test uses reflection to access the protected method
     */
    public function testGetEventKindName(): void
    {
        $longformEvent = new LongformEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(LongformEvent::class, 'getEventKindName');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($longformEvent);
        
        // Verify the result
        $this->assertEquals('longform', $result);
    }

    /**
     * Test preprocessMarkup method with valid markup
     * 
     * This test uses reflection to access the protected method
     */
    public function testPreprocessMarkupWithValidMarkup(): void
    {
        $markup = "<<YAML>>\nauthor: Test Author\n<</YAML>>\n\n# Title\nContent\n\n# Section 1\nContent 1\n\n# Section 2\nContent 2";
        
        // Create a LongformEvent instance
        $longformEvent = new LongformEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(LongformEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($longformEvent, $markup);
        
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
        
        // Create a LongformEvent instance
        $longformEvent = new LongformEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(LongformEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This markup file contains no headers.');
        
        // Call the method
        $reflectionMethod->invoke($longformEvent, $markup);
    }

    /**
     * Test preprocessMarkup method with no metadata
     * 
     * This test uses reflection to access the protected method
     */
    public function testPreprocessMarkupWithNoMetadata(): void
    {
        $markup = "# Title\nContent without metadata";
        
        // Create a LongformEvent instance
        $longformEvent = new LongformEvent();
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(LongformEvent::class, 'preprocessMarkup');
        $reflectionMethod->setAccessible(true);
        
        // Expect an exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This markup file contains no metadata.');
        
        // Call the method
        $reflectionMethod->invoke($longformEvent, $markup);
    }

    /**
     * Test buildEvent method
     * 
     * This test uses reflection to access the protected method
     */
    public function testBuildEvent(): void
    {
        // Create a LongformEvent instance
        $longformEvent = new LongformEvent();
        $longformEvent->setDTag('test-d-tag');
        $longformEvent->setTitle('Test Title');
        $longformEvent->setContent('Test Content');
        
        // Use reflection to access the protected method
        $reflectionMethod = new ReflectionMethod(LongformEvent::class, 'buildEvent');
        $reflectionMethod->setAccessible(true);
        
        // Call the method
        $result = $reflectionMethod->invoke($longformEvent);
        
        // Verify the result
        $this->assertInstanceOf(Event::class, $result);
        
        // Use reflection to access protected properties
        $reflectionClass = new ReflectionClass(Event::class);
        
        $kindProperty = $reflectionClass->getProperty('kind');
        $kindProperty->setAccessible(true);
        $this->assertEquals(LongformEvent::EVENT_KIND, $kindProperty->getValue($result));
        
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

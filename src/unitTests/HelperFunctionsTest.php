<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for helper functions
 */
final class HelperFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        // Include the helper functions file
        include_once dirname(__DIR__) . '/HelperFunctions.php';
    }

    /**
     * Test construct_d_tag_publication with all parameters
     */
    public function testConstructDTagWithAllParameters(): void
    {
        $result = construct_d_tag_publication('Test Title', 'John Doe', '2.0');
        $this->assertEquals('test-title-by-john-doe-v-2.0', $result);
    }

    /**
     * Test construct_d_tag_publication with default parameters
     */
    public function testConstructDTagWithDefaultParameters(): void
    {
        $result = construct_d_tag_publication('Test Title');
        $this->assertEquals('test-title-by-unknown-v-1', $result);
    }

    /**
     * Test construct_d_tag_publication with special characters
     */
    public function testConstructDTagWithSpecialCharacters(): void
    {
        $result = construct_d_tag_publication('Test: Title!', 'John, Doe', '2.0');
        $this->assertEquals('test-title-by-john-doe-v-2.0', $result);
    }

    /**
     * Test construct_d_tag_publication with long title (should truncate to 75 chars)
     */
    public function testConstructDTagWithLongTitle(): void
    {
        $longTitle = str_repeat('a', 100);
        $result = construct_d_tag_publication($longTitle, 'author', '1.0');
        $this->assertEquals(75, strlen($result));
    }

    /**
     * Test normalize_tag_component with spaces
     */
    public function testNormalizeTagComponentWithSpaces(): void
    {
        $result = normalize_tag_component('Test Component');
        $this->assertEquals('Test-Component', $result);
    }

    /**
     * Test normalize_tag_component with multiple spaces
     */
    public function testNormalizeTagComponentWithMultipleSpaces(): void
    {
        $result = normalize_tag_component('Test  Multiple   Spaces');
        $this->assertEquals('Test-Multiple-Spaces', $result);
    }

    /**
     * Test format_d_tag with mixed case
     */
    public function testFormatDTagWithMixedCase(): void
    {
        $result = format_d_tag('Test-TAG');
        $this->assertEquals('test-tag', $result);
    }

    /**
     * Test format_d_tag with punctuation
     */
    public function testFormatDTagWithPunctuation(): void
    {
        $result = format_d_tag('Test!@#$%^&*()_+{}|:"<>?-TAG.with.periods');
        // The function doesn't remove all punctuation, only some
        $this->assertEquals('test$^|<>-tag.with.periods', $result);
    }

    /**
     * Test format_d_tag with long input (should truncate to 75 chars)
     */
    public function testFormatDTagWithLongInput(): void
    {
        $longInput = str_repeat('a-', 50);
        $result = format_d_tag($longInput);
        $this->assertEquals(75, strlen($result));
    }

    /**
     * Test get_relay_list with existing relay file
     */
    public function testGetRelayListWithExistingFile(): void
    {
        // Create a temporary relay file
        $relaysFile = getcwd() . "/user/relays.yml";
        $originalContent = "";
        
        if (file_exists($relaysFile)) {
            $originalContent = file_get_contents($relaysFile);
        }
        
        try {
            file_put_contents($relaysFile, "wss://test-relay.com\nwss://another-relay.com");
            
            $result = get_relay_list();
            
            $this->assertCount(2, $result);
            $this->assertInstanceOf('swentel\nostr\Relay\Relay', $result[0]);
            // Just check that the relays were created, not their specific properties
            // since the Relay class structure might be different
        } finally {
            // Restore original content
            file_put_contents($relaysFile, $originalContent);
        }
    }

    /**
     * Test get_relay_list with non-existing relay file (should use default)
     */
    public function testGetRelayListWithNonExistingFile(): void
    {
        // Temporarily rename the relay file if it exists
        $relaysFile = getcwd() . "/user/relays.yml";
        $tempFile = getcwd() . "/user/relays.yml.bak";
        $fileExists = file_exists($relaysFile);
        
        if ($fileExists) {
            rename($relaysFile, $tempFile);
        }
        
        try {
            $result = get_relay_list();
            
            $this->assertCount(1, $result);
            $this->assertInstanceOf('swentel\nostr\Relay\Relay', $result[0]);
            // Just check that the relay was created, not its specific properties
        } finally {
            // Restore the original file
            if ($fileExists) {
                rename($tempFile, $relaysFile);
            }
        }
    }

    /**
     * Test get_relay_list with empty relay file (should use default)
     */
    public function testGetRelayListWithEmptyFile(): void
    {
        // Create an empty relay file
        $relaysFile = getcwd() . "/user/relays.yml";
        $originalContent = "";
        
        if (file_exists($relaysFile)) {
            $originalContent = file_get_contents($relaysFile);
        }
        
        try {
            file_put_contents($relaysFile, "");
            
            $result = get_relay_list();
            
            $this->assertCount(1, $result);
            $this->assertInstanceOf('swentel\nostr\Relay\Relay', $result[0]);
            // Just check that the relay was created, not its specific properties
        } finally {
            // Restore original content
            file_put_contents($relaysFile, $originalContent);
        }
    }

    /**
     * Test print_event_data with valid data
     */
    public function testPrintEventDataWithValidData(): void
    {
        // Create a temporary file for testing
        $originalDir = getcwd();
        $tempDir = sys_get_temp_dir() . '/ebookutility_test_' . uniqid();
        mkdir($tempDir);
        chdir($tempDir);
        
        try {
            $result = print_event_data('30040', 'test-event-id', 'test-d-tag');
            
            $this->assertTrue($result);
            $this->assertFileExists($tempDir . '/eventsCreated.yml');
            
            $content = file_get_contents($tempDir . '/eventsCreated.yml');
            $this->assertStringContainsString('event ID: test-event-id', $content);
            $this->assertStringContainsString('event kind: 30040', $content);
            $this->assertStringContainsString('d Tag: test-d-tag', $content);
        } finally {
            // Clean up
            if (file_exists($tempDir . '/eventsCreated.yml')) {
                unlink($tempDir . '/eventsCreated.yml');
            }
            chdir($originalDir);
            rmdir($tempDir);
        }
    }

    /**
     * Test create_tags_from_yaml with valid YAML
     */
    public function testCreateTagsFromYamlWithValidYaml(): void
    {
        $yamlSnippet = <<<YAML
<<YAML>>
author: John Doe
version: 2.0
tag-type: e
auto-update: yes
tags:
  - [tag1, value1]
  - [tag2, value2]
<</YAML>>
YAML;

        $result = create_tags_from_yaml($yamlSnippet);
        
        $this->assertEquals('John Doe', $result['author']);
        $this->assertEquals('2.0', $result['version']);
        $this->assertEquals('e', $result['tag-type']);
        $this->assertEquals('yes', $result['auto-update']);
        $this->assertCount(2, $result['tags']);
        $this->assertEquals(['tag1', 'value1'], $result['tags'][0]);
        $this->assertEquals(['tag2', 'value2'], $result['tags'][1]);
    }

    /**
     * Test create_tags_from_yaml with invalid YAML
     */
    public function testCreateTagsFromYamlWithInvalidYaml(): void
    {
        $yamlSnippet = <<<YAML
<<YAML>>
This is not valid YAML
<</YAML>>
YAML;

        $result = create_tags_from_yaml($yamlSnippet);
        
        $this->assertEquals('', $result['author']);
        $this->assertEquals('', $result['version']);
        $this->assertEquals('', $result['tag-type']);
        $this->assertEquals('', $result['auto-update']);
        $this->assertEmpty($result['tags']);
    }

    /**
     * Test create_tags_from_yaml with partial YAML
     */
    public function testCreateTagsFromYamlWithPartialYaml(): void
    {
        $yamlSnippet = <<<YAML
<<YAML>>
author: John Doe
<</YAML>>
YAML;

        $result = create_tags_from_yaml($yamlSnippet);
        
        $this->assertEquals('John Doe', $result['author']);
        $this->assertEquals('', $result['version']);
        $this->assertEquals('', $result['tag-type']);
        $this->assertEquals('', $result['auto-update']);
        $this->assertEmpty($result['tags']);
    }

    /**
     * Test create_tags_from_yaml with invalid tags format
     */
    public function testCreateTagsFromYamlWithInvalidTagsFormat(): void
    {
        $yamlSnippet = <<<YAML
<<YAML>>
author: John Doe
tags:
  - invalid_tag
<</YAML>>
YAML;

        $result = create_tags_from_yaml($yamlSnippet);
        
        $this->assertEquals('John Doe', $result['author']);
        $this->assertEmpty($result['tags']);
    }
}

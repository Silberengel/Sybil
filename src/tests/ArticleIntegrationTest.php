<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the eBookUtility application
 * 
 * These tests verify that the application can correctly process different types
 * of markup files and publish them as Nostr events.
 */
final class ArticleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Include necessary files
        // These files are now loaded by the bin/sybil script
        // No need to include them here as they're already loaded when the script runs
        
        // Mock helper functions have been removed
    }

    /**
     * Test that a file with a-tags can be processed correctly
     */
    public function testSourcefileHas_Atags(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/AesopsFables_testfile_a.adoc";
        $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published 30040 event with ID') !== false) {
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Publication event (a-tags) was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created 30040 event with ID', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that a file with e-tags can be processed correctly
     */
    public function testSourcefileHas_Etags(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/AesopsFables_testfile_e.adoc";
        $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published 30040 event with ID') !== false) {
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Publication event (e-tags) was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created 30040 event with ID', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that a basic AsciiDoc file can be processed correctly
     */
    public function testAsciidocBasic(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Asciidoctest_basic.adoc";
        $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published') !== false) {
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Basic AsciiDoc publication event was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that a blog-style file can be processed correctly
     */
    public function testBlogFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Blog_testfile.adoc";
        $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published') !== false) {
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Blog publication event was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that a Lorem Ipsum file can be processed correctly
     */
    public function testLoremIpsum(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/LoremIpsum.adoc";
        $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published') !== false) {
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Lorem Ipsum publication event was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that a relay test file can be processed correctly
     */
    public function testRelayTestFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/RelayTest.adoc";
        $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published') !== false) {
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Relay test publication event was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }
    
    /**
     * Test that a longform file can be processed correctly
     */
    public function testLongformFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Markdown_testfile.md";
        $return = shell_exec(command: 'sybil longform ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published longform event with ID') !== false) {
            $this->assertStringContainsString(needle: 'The longform article has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Longform event was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created longform event with ID', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that a wiki page file can be processed correctly
     */
    public function testWikiFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Wiki_testfile.adoc";
        $return = shell_exec(command: 'sybil wiki ' . $testFile . ' 2>&1');
        
        // Check if the event was published successfully or not
        if (strpos($return, 'Published wiki event with ID') !== false) {
            $this->assertStringContainsString(needle: 'The wiki page has been written.', haystack: $return);
        } else {
            // Issue an E_NOTICE when the event was not published
            trigger_error('Wiki event was not published to any relay', E_USER_NOTICE);
            $this->assertStringContainsString(needle: 'Created wiki event with ID', haystack: $return);
            $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
        }
    }

    /**
     * Test that the application works correctly when the relay list is empty
     * by using the default Citadel relay
     */
    public function testRelayListIsEmpty(): void
    {
        // save the relay list, to return it back to normal after the test
        $relaysFile = dirname(__DIR__, 2) . "/user/relays.yml";
        
        // Backup the original file content
        $originalContent = "";
        if (file_exists($relaysFile)) {
            $originalContent = file_get_contents($relaysFile);
        }

        try {
            // delete the contents of the file
            file_put_contents(filename: $relaysFile, data: "");

            // make sure that publication can still be printed using the default Citadel relay.
            $testFile = dirname(__DIR__) . "/testdata/testfiles/AesopsFables_testfile_a.adoc";
            $return = shell_exec(command: 'sybil publication ' . $testFile . ' 2>&1');
            
            // Check if the event was published successfully or not
            if (strpos($return, 'Published') !== false) {
                $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
            } else {
                // Issue an E_NOTICE when the event was not published
                trigger_error('Empty relay list publication event was not published to any relay', E_USER_NOTICE);
                $this->assertStringContainsString(needle: 'Created', haystack: $return);
                $this->assertStringContainsString(needle: 'The event was not published to any relay.', haystack: $return);
            }
        } finally {
            // Restore the original content regardless of test outcome
            file_put_contents($relaysFile, $originalContent);
        }
    }
}

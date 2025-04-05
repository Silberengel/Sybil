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
        // Don't include HelperFunctions.php to avoid function redefinition
        // require_once dirname(__DIR__) . '/HelperFunctions.php';
        require_once dirname(__DIR__) . '/BaseEvent.php';
        require_once dirname(__DIR__) . '/Tag.php';
        require_once dirname(__DIR__) . '/SectionEvent.php';
        require_once dirname(__DIR__) . '/PublicationEvent.php';
        require_once dirname(__DIR__) . '/LongformEvent.php';
        require_once dirname(__DIR__) . '/WikiEvent.php';
        
        // Mock helper functions have been removed
    }

    /**
     * Test that a file with a-tags can be processed correctly
     */
    public function testSourcefileHas_Atags(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/AesopsFables_testfile_a.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
        $this->assertStringContainsString(needle: 'Published 30040 event with a tags', haystack: $return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a file with e-tags can be processed correctly
     */
    public function testSourcefileHas_Etags(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/AesopsFables_testfile_e.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'Published 30040 event with e tags', haystack: $return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a basic AsciiDoc file can be processed correctly
     */
    public function testAsciidocBasic(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Asciidoctest_basic.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a blog-style file can be processed correctly
     */
    public function testBlogFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Blog_testfile.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a Lorem Ipsum file can be processed correctly
     */
    public function testLoremIpsum(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/LoremIpsum.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a relay test file can be processed correctly
     */
    public function testRelayTestFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/RelayTest.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }
    
    /**
     * Test that a longform file can be processed correctly
     */
    public function testLongformFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Markdown_testfile.md";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php longform ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'The longform article has been written.', haystack: $return);
    }

    /**
     * Test that a wiki page file can be processed correctly
     */
    public function testWikiFile(): void
    {
        $testFile = dirname(__DIR__) . "/testdata/testfiles/Wiki_testfile.adoc";
        $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php wiki ' . $testFile . ' 2>&1');
        
        $this->assertStringContainsString(needle: 'The wiki page has been written.', haystack: $return);
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
            $return = shell_exec(command: 'php ' . dirname(__DIR__, 2) . '/Sybil.php publication ' . $testFile . ' 2>&1');
            
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } finally {
            // Restore the original content regardless of test outcome
            file_put_contents($relaysFile, $originalContent);
        }
    }
}

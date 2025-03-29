<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
include_once 'HelperFunctions.php';
include_once 'SectionEvent.php';
include_once 'PublicationEvent.php';

/**
 * Integration tests for the eBookUtility application
 * 
 * These tests verify that the application can correctly process different types
 * of AsciiDoc files and publish them as Nostr events.
 */
final class IntegrationTest extends TestCase
{
    /**
     * Test that a file with a-tags can be processed correctly
     */
    public function testSourcefileHas_Atags(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/AesopsFables_testfile_a.adoc";
        $return = shell_exec(command: 'php Sybil.php publication '.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'Published 30040 event with a tags', haystack: $return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a file with e-tags can be processed correctly
     */
    public function testSourcefileHas_Etags(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/AesopsFables_testfile_e.adoc";
        $return = shell_exec(command: 'php Sybil.php publication '.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'Published 30040 event with e tags', haystack: $return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a basic AsciiDoc file can be processed correctly
     */
    public function testAsciidocBasic(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/Asciidoctest_basic.adoc";
        $return = shell_exec(command: 'php Sybil.php publication '.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a blog-style file can be processed correctly
     */
    public function testBlogFile(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/Blog_testfile.adoc";
        $return = shell_exec(command: 'php Sybil.php publication '.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a Lorem Ipsum file can be processed correctly
     */
    public function testLoremIpsum(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/LoremIpsum.adoc";
        $return = shell_exec(command: 'php Sybil.php publication '.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    /**
     * Test that a relay test file can be processed correctly
     */
    public function testRelayTestFile(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/RelayTest.adoc";
        $return = shell_exec(command: 'php Sybil.php publication'.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }
    
    /**
     * Test that a longform file can be processed correctly
     */
    public function testLongformFile(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/Markdown_testfile.md";
        $return = shell_exec(command: 'php Sybil.php longform'.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'The longform article has been written.', haystack: $return);
    }

    /**
     * Test that a wiki page file can be processed correctly
     */
    public function testWikiFile(): void
    {
        $testFile = getcwd()."/src/testdata/testfiles/Wiki_testfile.adoc";
        $return = shell_exec(command: 'php Sybil.php wiki'.$testFile);
        var_dump($return);
        $this->assertStringContainsString(needle: 'The wiki page has been written.', haystack: $return);
    }

    /**
     * Test that the application works correctly when the relay list is empty
     * by using the default Citadel relay
     */
    public function testRelayListIsEmpty(): void
    {
        // save the relay list, to return it back to normal after the test
        $relaysFile = getcwd()."/user/relays.yml";
        
        // Backup the original file content
        $originalContent = "";
        if (file_exists($relaysFile)) {
            $originalContent = file_get_contents($relaysFile);
        }

        try {
            // delete the contents of the file
            file_put_contents(filename: $relaysFile, data: "");

            // make sure that publication can still be printed using the default Citadel relay.
            $testFile = getcwd()."/src/testdata/testfiles/AesopsFables_testfile_a.adoc";
            $return = shell_exec(command: 'php Sybil.php publication '.$testFile);
            var_dump($return);
            $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
        } finally {
            // Restore the original content regardless of test outcome
            file_put_contents($relaysFile, $originalContent);
        }
    }
}

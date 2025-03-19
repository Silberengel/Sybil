<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
include_once 'helperFunctions.php';
include_once 'SectionEvent.php';
include_once 'PublicationEvent.php';

final class IntegrationTest extends TestCase
{
    public function testSourcefileHas_Atags(): void
    {
        $testFile =  getcwd()."/src/testdata/testfiles/AesopTest_a.yml";
        $return = shell_exec(command: 'php createPublication.php '.$testFile);
        $this->assertStringContainsString(needle: 'Published 30040 event with a tags', haystack: $return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    public function testSourcefileHas_Etags(): void
    {
        $testFile =  getcwd()."/src/testdata/testfiles/AesopTest_e.yml";
        $return = shell_exec(command: 'php createPublication.php '.$testFile);
        $this->assertStringContainsString(needle: 'Published 30040 event with e tags', haystack: $return);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    }

    
    public function testRelayListIsEmpty(): void
    {
        // save the relay list, to return it back to normal after the test
        $relaysFile = getcwd()."/user/relays.yml";
        $relaysRead = [];
        $relaysRead = file(filename: $relaysFile, flags: FILE_IGNORE_NEW_LINES);

        // delete the contents of the file
        file_put_contents(filename: $relaysFile, data: "");

        // make sure that publication can still be printed using the default Citadel relay.
        $testFile =  getcwd()."/src/testdata/testfiles/AesopTest_a.yml";
        $return = shell_exec(command: 'php createPublication.php '.$testFile);
        $this->assertStringContainsString(needle: 'The publication has been written.', haystack: $return);
    
        // put the original contents of the file back.
        foreach ($relaysRead as &$relay) {
            file_put_contents(filename: $relaysFile, data: $relay, flags: FILE_APPEND);
        }

    }
}
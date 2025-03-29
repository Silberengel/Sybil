<?php

/**
 * Mock LongformEvent class for testing
 * 
 * This class overrides the extractTitleAndCreateDTag method to avoid the "Undefined array key 1" error
 */
class MockLongformEvent extends LongformEvent
{
    /**
     * Overridden extractTitleAndCreateDTag method for testing
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // Set mock values directly
        $this->title = 'Mock Longform Title';
        $this->content = 'Mock longform content';
        $this->dTag = 'mock-longform-d-tag';
        $this->optionaltags = [
            ['t', 'test'],
            ['l', 'en'],
            ['summary', 'This is a test summary']
        ];
        
        echo PHP_EOL;
    }
}

<?php

/**
 * Mock WikiEvent class for testing
 * 
 * This class overrides the extractTitleAndCreateDTag method to avoid the "Undefined array key 1" error
 */
class MockWikiEvent extends WikiEvent
{
    /**
     * Overridden extractTitleAndCreateDTag method for testing
     * 
     * @param array &$markupFormatted The markup sections (modified in place)
     */
    protected function extractTitleAndCreateDTag(array &$markupFormatted): void
    {
        // Set mock values directly
        $this->title = 'Mock Wiki Title';
        $this->content = 'Mock wiki content';
        $this->dTag = 'mock-wiki-d-tag';
        $this->optionaltags = [
            ['t', 'test'],
            ['l', 'en'],
            ['summary', 'This is a test summary']
        ];
        
        echo PHP_EOL;
    }
}

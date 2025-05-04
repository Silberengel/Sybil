<?php

namespace Sybil\Tests\Publications\Liturgy;

use DOMDocument;
use DOMXPath;

class DivineOfficeScraper {
    private $logger;
    private $curlManager;
    private $baseUrl = 'https://divineoffice.org';

    public function __construct($logger = null, $curlManager = null) {
        $this->logger = $logger ?? new Logger();
        $this->curlManager = $curlManager ?? new CurlManager($this->logger);
    }

    public function scrape() {
        try {
            $this->logger->info('Starting Divine Office scraping');
            
            // Get the main page
            $html = $this->curlManager->get($this->baseUrl);
            
            // Parse the HTML
            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new DOMXPath($doc);
            
            // Find all hymn containers
            $hymnNodes = $xpath->query("//div[contains(@class, 'hymn-container')]");
            
            $hymns = [];
            foreach ($hymnNodes as $node) {
                $hymn = $this->extractHymnData($node);
                if ($hymn) {
                    $hymns[] = $hymn;
                }
            }
            
            $this->logger->info('Scraping completed', ['hymn_count' => count($hymns)]);
            return $hymns;
            
        } catch (\Exception $e) {
            $this->logger->error('Scraping failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function extractHymnData($node) {
        try {
            $title = $this->getNodeText($node, './/h3');
            $content = $this->getNodeText($node, './/div[contains(@class, "hymn-content")]');
            
            if (!$title || !$content) {
                return null;
            }
            
            return [
                'title' => $title,
                'content' => $content,
                'source' => 'Divine Office',
                'url' => $this->baseUrl
            ];
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to extract hymn data: ' . $e->getMessage());
            return null;
        }
    }

    private function getNodeText($node, $xpath) {
        $result = $node->getElementsByTagName('*');
        foreach ($result as $element) {
            if ($element->nodeType === XML_ELEMENT_NODE && $element->tagName === 'div' && strpos($element->getAttribute('class'), 'hymn-container') !== false) {
                return trim($element->textContent);
            }
        }
        return null;
    }
} 
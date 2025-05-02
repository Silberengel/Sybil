<?php

/**
 * DivineOffice Scraper
 * 
 * This class handles the scraping and processing of the Divine Office website.
 * It follows a three-phase approach:
 * 1. URL Collection: Gathers all valid URLs for each day
 * 2. Content Fetching: Retrieves content for each URL
 * 3. File Generation: Creates AsciiDoc files from the content
 */
class DivineOfficeScraper {
    // URL and path constants
    private const BASE_URL = 'https://divineoffice.org';
    private const OUTPUT_DIR = 'output_modern';
    
    // Date format constants
    private const DATE_FORMAT_INPUT = 'Y-m-d';
    private const DATE_FORMAT_URL = 'Ymd';
    
    // HTTP constants
    private const HTTP_SUCCESS = 200;
    private const HTTP_HEADERS = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Cache-Control: max-age=0',
        'Referer: https://divineoffice.org/',
        'Cookie: iAmFromTheUSA=1'
    ];
    
    // Content selectors
    private const CONTENT_SELECTORS = [
        "//div[contains(@class, 'entry')]",
        "//div[contains(@class, 'entry-content')]",
        "//article",
        "//div[contains(@class, 'content')]",
        "//div[contains(@class, 'main-content')]",
        "//div[contains(@class, 'liturgical-text')]",
        "//div[contains(@class, 'prayer-text')]",
        "//div[contains(@class, 'prayer-content')]"
    ];
    
    // Tags to remove from content
    private const TAGS_TO_REMOVE = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'table', 'tbody', 'tr', 'td'];
    
    // 404 page text
    private const ERROR_404_TEXT = "But don't worry—just like the shepherd rejoices when he finds the lost sheep, we're here to help you find your way back.";
    
    private $hours = [
        'or' => 'Office of Readings',
        'mp' => 'Morning Prayer',
        'ep' => 'Evening Prayer',
        'inv' => 'Invitatory',
        'about' => 'About'
    ];
    
    private $logger;
    private $curl;
    private array $warnings = [];
    
    /**
     * Constructor
     * Initializes the scraper with a logger and curl manager
     */
    public function __construct() {
        $this->logger = new Logger();
        $this->curl = new CurlManager($this->logger, self::HTTP_HEADERS, self::HTTP_SUCCESS);
    }
    
    /**
     * Main execution method
     * Orchestrates the three-phase scraping process:
     * 1. URL Collection
     * 2. Content Fetching
     * 3. File Generation
     *
     * @param string $start_date Start date in YYYY-MM-DD format
     * @param string $end_date End date in YYYY-MM-DD format
     * @return void
     */
    public function run(string $start_date, string $end_date): void {
        if (!$this->isValidDate($start_date) || !$this->isValidDate($end_date)) {
            $this->logger->error("Invalid date format. Please use " . self::DATE_FORMAT_INPUT . " format.");
            return;
        }
        $this->logger->info("Starting Divine Office scraping process");
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        $all_data = [];
        while ($current <= $end) {
            $formatted_date = date(self::DATE_FORMAT_URL, $current);
            $this->logger->info("Processing date: $formatted_date");
            $main_url = self::BASE_URL . "/?date={$formatted_date}";
            $main_html = $this->fetchMainPage($main_url);
            if (!$main_html) {
                $current = strtotime('+1 day', $current);
                continue;
            }
            $day_urls = $this->extractHourUrls($main_html, $formatted_date);

            // Initialize $lines ONCE per day
            $lines = [];
            
            // Add document-level header
            $full_date = date('l, F j, Y', $current);
            $lines[] = '= Liturgy of the Hours for ' . $full_date . "\n";
            $lines[] = "////
<<YAML>>
author: \"The Roman Catholic Church\"
version: \"New American Bible\"
tag-type: \"a\"
auto-update: \"ask\"
tags:
  - [\"image\", \"https://i.nostr.build/H4fGbUV2asYX1TGl.png\"]
  - [\"type\", \"book\"]
  - [\"l\", \"en, ISO-639-1\"]
  - [\"reading-direction\", \"left-to-right, top-to-bottom\"]
  - [\"t\", \"religion\"]
  - [\"t\", \"liturgy\"]
  - [\"summary\", \"Excerpts from today's Divine Office, also known as The Liturgy of the Hours. The full text, read-alouds, and all hours are available on the DivineOffice.org website.\"]
  - [\"published_by\", \"DivineOffice.org\"]
  - [\"source\", \"https://divineoffice.org/\"]
<</YAML>>
////";

            // == Office of Readings
            $lines[] = '== Office of Readings';

            // Invitatory (if present)
            if (isset($day_urls['inv'])) {
                $inv_html = $this->curl->fetch($day_urls['inv']);
                if ($inv_html) {
                    $inv_raw = $this->extractLiturgicalText($inv_html, 'Invitatory');
            
                    $lines[] = '=== Invitatory';
            
                    $inv_lines = explode("\n", ltrim($inv_raw));
                    foreach ($inv_lines as $line) {
                        if (trim($line) !== '') {
                            $lines[] = $line;
                        }
                    }
                }
            }
            // Office of Readings content
            if (isset($day_urls['or'])) {
                $or_html = $this->curl->fetch($day_urls['or']);
                if ($or_html && !$this->is404Page($or_html)) {
                    $or_text = $this->extractLiturgicalText($or_html, 'Office of Readings');
                    if (!empty($or_text)) {
                        $or_lines = explode("\n", $or_text);
                        foreach ($or_lines as $line) {
                            if (trim($line) !== '') {
                                $lines[] = $line;
                            }
                        }
                    }
                } else {
                    $this->addWarning("Failed to fetch Office of Readings for $formatted_date");
                }
            }

            // == Morning Prayer
            $lines[] = '== Morning Prayer' . "\n";

            // About section (if present)
            if (isset($day_urls['about'])) {
                $about_html = $this->curl->fetch($day_urls['about']);
                if ($about_html) {
                    $dom = new DOMDocument();
                    @$dom->loadHTML($about_html, LIBXML_NOERROR | LIBXML_NOWARNING);
                    $xpath = new DOMXPath($dom);
                    $img = $xpath->query("//div[contains(@class, 'about-today-image')]//img")->item(0);
                    $img_url = $img ? $img->getAttribute('src') : '';
                    $about_raw = $this->extractLiturgicalText($about_html, 'About');
                    $about_lines = preg_split('/\r?\n/', $about_raw, 2);
                    
                    $lines[] = '=== About ';
                    if (isset($about_lines[0])) {
                        $lines[] = "[.text-center]";
                        $lines[] = "*" . trim($about_lines[0]) . "*";
                        if ($img_url) {
                            $lines[] = "\n" . "image::" . $img_url . "[About image, 200]";
                        }

                        if (isset($about_lines[1]) && trim($about_lines[1]) !== '') {
                            $abouttext = substr(trim($about_lines[1]), 0, 500);
                            $lines[] = $abouttext;
                            $lines[] = "\n\n" . $day_urls['about'] . "[...Read the entire excerpt.]";
                        }
                    } else {
                        $lines[] = $about_raw;
                    }
                }
            }
            // Morning Prayer content
            if (isset($day_urls['mp'])) {
                $mp_html = $this->curl->fetch($day_urls['mp']);
                if ($mp_html && !$this->is404Page($mp_html)) {
                    $mp_text = $this->extractLiturgicalText($mp_html, 'Morning Prayer');
                    if (!empty($mp_text)) {
                        $mp_lines = explode("\n", $mp_text);
                        foreach ($mp_lines as $line) {
                            if (trim($line) !== '') {
                                $lines[] = $line;
                            }
                        }
                    }
                } else {
                    $this->addWarning("Failed to fetch Morning Prayer for $formatted_date");
                }
            }

            // == Evening Prayer
            $lines[] = '== Evening Prayer' . "\n";
            
            if (isset($day_urls['ep'])) {
                $ep_html = $this->curl->fetch($day_urls['ep']);
                if ($ep_html && !$this->is404Page($ep_html)) {
                    $ep_text = $this->extractLiturgicalText($ep_html, 'Evening Prayer');
                    if (!empty($ep_text)) {
                        $ep_lines = explode("\n", $ep_text);
                        foreach ($ep_lines as $line) {
                            if (trim($line) !== '') {
                                $lines[] = $line;
                            }
                        }
                    }
                } else {
                    $this->addWarning("Failed to fetch Evening Prayer for $formatted_date");
                }
            }
            if (!empty($lines)) {
                $all_data[$formatted_date] = $lines;
            }
            $current = strtotime('+1 day', $current);
        }
        $this->logger->info("\nWriting AsciiDoc files...");
        foreach ($all_data as $day_str => $lines) {
            $this->saveAsciiDoc($day_str, $lines);
        }
        $this->printWarnings();
        $this->logger->info("Processing complete");
    }

    /**
     * Validates that a date string is in the correct format
     *
     * @param string $date Date string to validate
     * @return bool True if date is valid, false otherwise
     */
    private function isValidDate(string $date): bool {
        $d = DateTime::createFromFormat(self::DATE_FORMAT_INPUT, $date);
        return $d && $d->format(self::DATE_FORMAT_INPUT) === $date;
    }

    /**
     * Fetches the main page for a specific date
     * Handles errors and 404 detection
     *
     * @param string $url URL to fetch
     * @return string|null HTML content if successful, null if failed
     */
    private function fetchMainPage(string $url): ?string {
        $this->logger->debug("Fetching main page: $url");
        
        $html = $this->curl->fetch($url);
        if (!$html) {
            $this->logger->error("Failed to fetch main page: $url");
            return null;
        }
        
        if ($this->is404Page($html)) {
            $this->logger->error("404 page detected for main URL: $url");
            return null;
        }
        
        return $html;
    }
    
    /**
     * Extracts hour URLs from the main page HTML
     * Maps URLs to their corresponding hour names
     *
     * @param string $html Main page HTML content
     * @param string $formatted_date Date in YYYYMMDD format
     * @return array<string, string> Array of hour URLs indexed by hour name
     */
    private function extractHourUrls(string $html, string $formatted_date): array {
        $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    $hour_links = $xpath->query("//a[contains(@href, '/?date={$formatted_date}')]");
    $day_urls = [];

    foreach ($hour_links as $link) {
        $href = $link->getAttribute('href');
        $text = trim($link->textContent);
    
        // Use the href as-is if it's absolute, otherwise prepend BASE_URL
        $full_url = (strpos($href, 'http') === 0) ? $href : self::BASE_URL . $href;
    
        // Map the link to the correct hour key
        if (preg_match('/\\/or-.*\\/?date=/', $href) || strpos($text, 'Office') !== false) {
            $day_urls['or'] = $full_url;
        } elseif (preg_match('/\\/mp-.*\\/?date=/', $href) || strpos($text, 'Morning') !== false) {
            $day_urls['mp'] = $full_url;
        } elseif (preg_match('/\\/ep-.*\\/?date=/', $href) || strpos($text, 'Evening') !== false) {
            $day_urls['ep'] = $full_url;
        } elseif (preg_match('/\\/inv-.*\\/?date=/', $href) || strpos($text, 'Invitatory') !== false) {
            $day_urls['inv'] = $full_url;
        } elseif (preg_match('/\\/about-.*\\/?date=/', $href) || strpos($text, 'About') !== false) {
            $day_urls['about'] = $full_url;
        }
    }

    if (empty($day_urls)) {
        $this->addWarning("No valid hour URLs found for date $formatted_date");
    }

    return $day_urls;
    }
    
    /**
     * Checks if HTML content contains the 404 page text
     *
     * @param string $html HTML content to check
     * @return bool True if 404 page detected, false otherwise
     */
    private function is404Page(string $html): bool {
        return strpos($html, self::ERROR_404_TEXT) !== false;
    }
    
    /**
     * Saves content as an AsciiDoc file
     * Creates output directory if it doesn't exist
     *
     * @param string $day_str Date string in YYYYMMDD format
     * @param array<string> $lines Array of lines to save
     * @return void
     */
    private function saveAsciiDoc(string $day_str, array $lines): void {
        $output_dir = __DIR__ . '/' . self::OUTPUT_DIR;
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        $out_path = "$output_dir/{$day_str}.adoc";
        $f = fopen($out_path, 'w');
        $day_link = self::BASE_URL . "/?date={$day_str}";

        foreach ($lines as $line) {

            if (str_contains($line, "== ") || str_contains($line, "=== ")) {
                fwrite($f, "\n\n" . $line . "\n\n" . "[%hardbreaks]" . "\n\n");
            }
            else {
                fwrite($f, $line . "\n");
            }
        }    
        
        fwrite($f, $day_link . "[View on DivineOffice.org]" . "\n\n");

        fclose($f);
        $this->logger->info("Saved $out_path");
    }
    
    /**
     * Sanitizes text for AsciiDoc format
     * Handles special characters and ensures proper escaping
     *
     * @param string $text Text to sanitize
     * @return string Sanitized text
     */
    private function sanitizeText(string $text): string {
        // Replace smart quotes with straight quotes
        $text = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9A", "\xE2\x80\x9B", "\xE2\x80\x94", "\xE2\x80\x93"],
            ["\"", "\"", "'", "'", "'", "'", "--", "-"],
            $text
        );
        
        // Handle other special characters
        $text = str_replace(
            ["\xE2\x80\xA6", "\xE2\x80\xA6", "\xE2\x80\xA6"],
            "...",
            $text
        );
        
        // Escape any remaining special characters that might cause issues
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        
        return $text;
    }

    /**
     * Extracts liturgical text from HTML content
     * Processes headings, paragraphs, lists, and about section
     * Removes unwanted HTML tags
     * Applies formatting: bold for red text, newlines for — lines
     *
     * @param string $html HTML content to process
     * @param string $hour_name Optional hour name for logging
     * @return string Extracted and formatted text
     */
    private function extractLiturgicalText(string $html, string $hour_name = ''): string {
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // Remove audio and video elements
        foreach (['audio', 'video'] as $tag) {
            foreach ($xpath->query("//$tag") as $element) {
                $element->parentNode->removeChild($element);
            }
        }

        $main = null;
        foreach (self::CONTENT_SELECTORS as $selector) {
            $main = $xpath->query($selector)->item(0);
            if ($main) {
                $msg = "Found content using selector: $selector";
                if ($hour_name) $msg .= " for $hour_name";
                $this->logger->debug($msg);
                break;
            }
        }

        if (!$main) {
            $this->addWarning("Could not find main content container" . ($hour_name ? " for $hour_name" : ""));
            return '';
        }

        // Remove unwanted tags
        foreach (self::TAGS_TO_REMOVE as $tag_name) {
            foreach (iterator_to_array($main->getElementsByTagName($tag_name)) as $tag) {
                $tag->parentNode->removeChild($tag);
            }
        }

        $text = '';
        $lines = [];
        $skip_nodes = [];
        // Use XPath to get all <p>, <div>, and header tags at any depth
        $all_blocks = $xpath->query('.//p | .//div | .//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $main);

        $logger = $this->logger;

        // Define the processing function inside extractLiturgicalText
        $processLiturgicalLine = function($txt) use (&$lines, &$expect_psalm_subtitle, &$logger) {
            // Sanitize the text before processing
            $txt = $this->sanitizeText($txt);
            
            // Skip arrow-up and to top text
            if (preg_match('/^(↑|to top|↑\s+to top)$/i', $txt)) {
                $logger->debug('Skipping line (arrow/top): [' . $txt . ']');
                return;
            }

            // Ribbon Placement
            if (preg_match('/^(Ribbon Placement:)/i', $txt)) {
                $lines[] = "\n\n";
                $lines[] = "[%hardbreaks]";
                $lines[] = "_" . $txt . "_";
                return;
            }

            // Special centered lines for prayer section titles
            if (preg_match('/^(Office of Readings for |Morning Prayer for |Evening Prayer for )/i', $txt)) {
                $lines[] = "\n\n";
                $lines[] = "[.text-center]";
                $lines[] = "[%hardbreaks]";
                $lines[] = "*" . $txt . "*";
                return;
            }

            // Centered Roman numerals on their own line
            if (preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/i', $txt)) {
                $lines[] = "\n\n";
                $lines[] = "[.text-center]";
                $lines[] = "[%hardbreaks]";
                $lines[] = $txt;
                return;
            }

            // Sub-section headers (level 3)
            if (preg_match('/^(RESPONSORY|READINGS|READING|PSALMODY|CONCLUDING PRAYER|Acclamation|CANTICLE OF MARY|Canticle|INTERCESSIONS|DISMISSAL|Psalm-prayer|Psalm\s+\d+(?::\d+(?:-\d+)?)?)(.*)$/i', $txt, $m)) {
                $header = trim($m[1]);
                $lines[] = "=== " . $header;
                
                if (preg_match('/\\.$/', $header)) {
                    $lines[] = "";
                }
            
                if (isset($m[2]) && trim($m[2]) !== '') {
                    $subtitle = "_" . trim($m[2]) . "_";
                    $lines[] = $subtitle;
                    if (preg_match('/\\.$/', $subtitle)) {
                        $lines[] = "";
                    }
                }
                $logger->debug('Appending to lines (default sub-section header): [=== ' . $header . ']');
                return;
            }

            // Centered block for reading headers
            if (preg_match('/^(First reading|Second reading|Third reading)$/i', $txt)) {
                $lines[] = "[.text-center]";
                $lines[] = $txt;
                $GLOBALS['expect_centered_reading'] = 2; // Expect up to 2 more lines for the centered block
                return;
            }

            // Handle lines after reading header for centered block
            if (isset($GLOBALS['expect_centered_reading']) && $GLOBALS['expect_centered_reading'] > 0 && trim($txt) !== '') {
                $lines[] = "_" . $txt . "_";
                $lines[] = "[%hardbreaks]";
                $GLOBALS['expect_centered_reading']--;
                // After the last italicized line, close the block with [%hardbreaks]
                if ($GLOBALS['expect_centered_reading'] === 0) {
                    unset($GLOBALS['expect_centered_reading']);
                }
                return;
            }

            // Antiphon lines (italic)
            if (preg_match('/^Ant\.(.*)$/i', $txt, $m)) {
                $ant = '_Ant._' . ($m[1] ? ' ' . trim($m[1]) : '');
                $lines[] = "[%hardbreaks]";
                $lines[] = $ant;
                $lines[] = "[%hardbreaks]";
                $logger->debug('Appending to lines (antiphon): [' . $ant . ']');
                return;
            }

            // Em dash lines
            if (preg_match('/^—(.*)$/u', $txt, $m)) {
                $dash = '— ' . ($m[1] ? trim($m[1]) : '');
                $lines[] = "\n" . $dash . "\n\n";
                $lines[] = "[%hardbreaks]";
                $logger->debug('Appending to lines (emdash): [' . $dash . ']');
                return;
            }

            // Normal content
            if ($txt !== '') {
                // Check if the next line will be a dash line
                if (isset($GLOBALS['next_line']) && preg_match('/^—(.*)$/u', $GLOBALS['next_line'])) {
                    $lines[] = "[%hardbreaks]";
                    $lines[] = "\n" . $txt;
                } else {
                    $lines[] = $txt;
                }
                $logger->debug('Appending to lines (normal): [' . $txt . ']');
            } else {
                $logger->debug('Skipping empty line');
            }
        };

        foreach ($all_blocks as $node) {
            // Skip nodes already processed as part of a hymn container
            if (isset($skip_nodes[spl_object_hash($node)])) {
                continue;
            }
            // Restore hymn container handling before splitting lines
            if ($node->nodeType === XML_ELEMENT_NODE && $node->tagName === 'div' && strpos($node->getAttribute('class'), 'hymn-container') !== false) {
                $this->logger->debug('Found hymn container');
                $lines[] = "=== HYMN";
                $first = true;
            foreach ($node->getElementsByTagName('p') as $p) {
                $stanza = trim($p->textContent);
                if ($first && preg_match('/^HYMN$/i', $stanza)) {
                    $this->logger->debug('Skipping redundant HYMN stanza');
                    $first = false;
                    $skip_nodes[spl_object_hash($p)] = true;
                    continue;
                }
                $first = false;
                if ($stanza !== '') {
                    $lines[] = $stanza;
                    $this->logger->debug('Appending to lines (hymn stanza): [' . $stanza . ']');
                } else {
                    $this->logger->debug('Skipping empty hymn stanza');
                }
                $skip_nodes[spl_object_hash($p)] = true; // Mark as processed
            }
                continue;
            }
            $txt = trim($node->textContent);
            if (strpos($txt, "\n") !== false) {
                $split_lines = preg_split('/\r?\n/', $txt);
                foreach ($split_lines as $i => $split_line) {
                    $split_line = trim($split_line);
                    if ($split_line !== '') {
                        // Store the next line if it exists
                        $GLOBALS['next_line'] = isset($split_lines[$i + 1]) ? trim($split_lines[$i + 1]) : null;
                        $processLiturgicalLine($split_line);
                    }
                }
                continue;
            }
            // For single lines, look ahead to the next node
            $next_node = $node->nextSibling;
            while ($next_node && $next_node->nodeType !== XML_ELEMENT_NODE) {
                $next_node = $next_node->nextSibling;
            }
            $GLOBALS['next_line'] = $next_node ? trim($next_node->textContent) : null;
            $processLiturgicalLine($txt);
        }

        $text = implode("\n", $lines);

        if (empty($text)) {
            $this->addWarning("Extracted text is empty" . ($hour_name ? " for $hour_name" : ""));
        } else {
            $msg = "Successfully extracted " . strlen($text) . " characters of text";
            if ($hour_name) $msg .= " for $hour_name";
            $this->logger->debug($msg);
        }

        return $text;
    }

    /**
     * Adds a warning to the collection
     *
     * @param string $message Warning message to add
     * @return void
     */
    private function addWarning(string $message): void {
        $this->warnings[] = $message;
        $this->logger->warning($message);
    }
    
    /**
     * Prints all collected warnings
     *
     * @return void
     */
    private function printWarnings(): void {
        if (!empty($this->warnings)) {
            $this->logger->info("\nWarnings that may need manual attention:");
            foreach ($this->warnings as $warning) {
                $this->logger->info("  - $warning");
            }
            $this->logger->info("");
        }
    }
}

/**
 * Logger class for consistent logging
 * Provides different log levels with timestamps
 */
class Logger {
    /**
     * Logs an informational message
     *
     * @param string $message Message to log
     * @return void
     */
    public function info(string $message): void {
        echo date('Y-m-d H:i:s') . " [INFO] $message\n";
    }
    
    /**
     * Logs an error message
     *
     * @param string $message Message to log
     * @return void
     */
    public function error(string $message): void {
        echo date('Y-m-d H:i:s') . " [ERROR] $message\n";
    }
    
    /**
     * Logs a warning message
     *
     * @param string $message Message to log
     * @return void
     */
    public function warning(string $message): void {
        echo date('Y-m-d H:i:s') . " [WARNING] $message\n";
    }
    
    /**
     * Logs a debug message
     *
     * @param string $message Message to log
     * @return void
     */
    public function debug(string $message): void {
        echo date('Y-m-d H:i:s') . " [DEBUG] $message\n";
    }
}

/**
 * CurlManager class for handling HTTP requests
 * Manages curl operations with consistent headers and error handling
 */
class CurlManager {
    private array $headers;
    private Logger $logger;
    private int $successCode;
    
    /**
     * Constructor
     * Initializes the curl manager with a logger and headers
     *
     * @param Logger $logger Logger instance
     * @param array $headers HTTP headers to use for requests
     * @param int $successCode HTTP success code to check for
     */
    public function __construct(Logger $logger, array $headers, int $successCode) {
        $this->logger = $logger;
        $this->headers = $headers;
        $this->successCode = $successCode;
    }
    
    /**
     * Fetches content from a URL
     * Handles curl errors and HTTP status codes
     *
     * @param string $url URL to fetch
     * @return string|null HTML content if successful, null if failed
     */
    public function fetch(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        
        $html = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $this->logger->error("Curl error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== $this->successCode) {
            $this->logger->error("HTTP error: $http_code for URL: $url");
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        return $html;
    }
}
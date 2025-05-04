<?php

namespace Sybil\Utility\Format;

use Psr\Log\LoggerInterface;
use Sybil\Utility\Log\LoggerFactory;
use InvalidArgumentException;
use Parsedown;
use DOMDocument;
use DOMXPath;

class ScriptoriumConverter
{
    private LoggerInterface $logger;
    private Parsedown $parsedown;

    public function __construct()
    {
        $this->logger = LoggerFactory::createLogger('scriptorium_converter');
        $this->parsedown = new Parsedown();
    }

    /**
     * Convert a document to AsciiDoc format
     *
     * @param string $inputPath Path to input file
     * @param string $outputPath Path to output file
     * @param string $title Document title
     * @return bool True if conversion was successful
     * @throws InvalidArgumentException If the input file format is not supported
     */
    public function convert(string $inputPath, string $outputPath, string $title): bool
    {
        try {
            $extension = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));
            $content = file_get_contents($inputPath);

            if ($content === false) {
                throw new \RuntimeException("Failed to read input file: $inputPath");
            }

            $adocContent = match ($extension) {
                'txt' => $this->convertTxt($content, $title),
                'rtf' => $this->convertRtf($content, $title),
                'html' => $this->convertHtml($content, $title),
                'md' => $this->convertMarkdown($content, $title),
                default => throw new InvalidArgumentException("Unsupported file format: $extension")
            };

            // Ensure the document has at least one section
            if (!str_contains($adocContent, "\n==")) {
                $adocContent .= "\n\n== Introduction\n\nThis section was automatically added to ensure proper document structure.";
            }

            $result = file_put_contents($outputPath, $adocContent);
            
            if ($result === false) {
                throw new \RuntimeException("Failed to write output file: $outputPath");
            }

            $this->logger->info('Document converted successfully', [
                'input' => $inputPath,
                'output' => $outputPath,
                'format' => $extension
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Convert plain text to AsciiDoc
     */
    private function convertTxt(string $content, string $title): string
    {
        // Split content into paragraphs and clean up
        $paragraphs = array_filter(
            array_map('trim', explode("\n\n", $content)),
            function($p) { return !empty($p); }
        );
        
        // Create AsciiDoc content
        $adoc = "= $title\n\n";
        
        // Use first paragraph as introduction if it exists
        if (!empty($paragraphs)) {
            $adoc .= "== Introduction\n\n" . $this->cleanText(array_shift($paragraphs)) . "\n\n";
        }
        
        // Convert remaining paragraphs to sections
        foreach ($paragraphs as $index => $paragraph) {
            $adoc .= "== Section " . ($index + 1) . "\n\n" . $this->cleanText($paragraph) . "\n\n";
        }
        
        return $adoc;
    }

    /**
     * Convert RTF to AsciiDoc
     */
    private function convertRtf(string $content, string $title): string
    {
        // Basic RTF to plain text conversion
        // Remove RTF control words and groups
        $plainText = preg_replace('/\\\\([a-z]+|\\d+)(-?\\d+)?[ ]?/', '', $content);
        $plainText = preg_replace('/\\{[^}]*\\}/', '', $plainText);
        
        // Remove remaining RTF control characters
        $plainText = preg_replace('/\\\\[a-z]+[0-9]?/', '', $plainText);
        $plainText = preg_replace('/\\\\[\\\'][0-9a-f]{2}/', '', $plainText);
        
        // Convert line endings
        $plainText = str_replace(["\r\n", "\r"], "\n", $plainText);
        
        // Remove multiple newlines
        $plainText = preg_replace('/\n{3,}/', "\n\n", $plainText);
        
        // Convert to AsciiDoc
        return $this->convertTxt($plainText, $title);
    }

    /**
     * Convert HTML to AsciiDoc
     */
    private function convertHtml(string $content, string $title): string
    {
        // Create DOM document with proper error handling
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), 
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR);
        libxml_clear_errors();
        
        // Initialize AsciiDoc content
        $adoc = "= $title\n\n";
        
        // Process headings and content
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //p | //img | //ul | //ol | //li | //blockquote | //pre | //code | //table | //tr | //td | //th');
        
        $inList = false;
        $listType = '';
        $inTable = false;
        $inCodeBlock = false;
        
        foreach ($nodes as $node) {
            switch ($node->nodeName) {
                case 'h1':
                    $adoc .= "\n== " . $this->cleanText($node->textContent) . "\n\n";
                    break;
                case 'h2':
                    $adoc .= "\n=== " . $this->cleanText($node->textContent) . "\n\n";
                    break;
                case 'h3':
                    $adoc .= "\n==== " . $this->cleanText($node->textContent) . "\n\n";
                    break;
                case 'h4':
                case 'h5':
                case 'h6':
                    $adoc .= "\n===== " . $this->cleanText($node->textContent) . "\n\n";
                    break;
                case 'p':
                    $adoc .= $this->cleanText($node->textContent) . "\n\n";
                    break;
                case 'img':
                    $src = $node->getAttribute('src');
                    $alt = $node->getAttribute('alt') ?: 'Image';
                    $adoc .= "image::$src[$alt]\n\n";
                    break;
                case 'ul':
                case 'ol':
                    $inList = true;
                    $listType = $node->nodeName === 'ul' ? '*' : '.';
                    break;
                case 'li':
                    if ($inList) {
                        $adoc .= $listType . " " . $this->cleanText($node->textContent) . "\n";
                    }
                    break;
                case 'blockquote':
                    $adoc .= "\n[quote]\n--\n" . $this->cleanText($node->textContent) . "\n--\n\n";
                    break;
                case 'pre':
                case 'code':
                    if (!$inCodeBlock) {
                        $adoc .= "\n[source]\n----\n";
                        $inCodeBlock = true;
                    } else {
                        $adoc .= "----\n\n";
                        $inCodeBlock = false;
                    }
                    break;
                case 'table':
                    $inTable = true;
                    $adoc .= "\n|===\n";
                    break;
                case 'tr':
                    if ($inTable) {
                        $adoc .= "\n";
                    }
                    break;
                case 'th':
                case 'td':
                    if ($inTable) {
                        $adoc .= "|" . $this->cleanText($node->textContent);
                    }
                    break;
            }
        }
        
        if ($inTable) {
            $adoc .= "\n|===\n\n";
        }
        
        return $adoc;
    }

    /**
     * Convert Markdown to AsciiDoc
     */
    private function convertMarkdown(string $content, string $title): string
    {
        // First convert to HTML using Parsedown
        $html = $this->parsedown->text($content);
        
        // Then convert HTML to AsciiDoc
        return $this->convertHtml($html, $title);
    }

    /**
     * Clean text content
     */
    private function cleanText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Convert special characters
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Escape AsciiDoc special characters
        $text = str_replace(['*', '_', '`', '#', '>', '<', '[', ']'], ['\*', '\_', '\`', '\#', '\>', '\<', '\[', '\]'], $text);
        
        return trim($text);
    }
} 
<?php

namespace Sybil\Utility\Format;

use Sybil\Exception\FormatException;
use Psr\Log\LoggerInterface;
use Sybil\Utility\Log\LoggerFactory;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Utility class for working with YAML data
 * 
 * This class provides utility functions for working with YAML data,
 * including parsing and formatting.
 */
class YamlUtility
{
    private static ?LoggerInterface $logger = null;
    private const MAX_YAML_LOG_LENGTH = 1000;

    private static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createLogger('yaml_utility');
        }
        return self::$logger;
    }

    /**
     * Parse YAML string
     *
     * @param string $yaml The YAML string to parse
     * @return mixed The parsed data
     * @throws FormatException If parsing fails
     */
    public static function parse(string $yaml)
    {
        try {
            $data = Yaml::parse($yaml);
            
            self::getLogger()->debug('YAML parsed successfully', [
                'length' => strlen($yaml)
            ]);
            
            return $data;
        } catch (ParseException $e) {
            self::getLogger()->error('Failed to parse YAML', [
                'error' => $e->getMessage(),
                'yaml_preview' => self::sanitizeForLogging($yaml)
            ]);
            throw new FormatException('Failed to parse YAML: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Dump data to YAML string
     *
     * @param mixed $data The data to dump
     * @param int $inline The level where you switch to inline YAML
     * @param int $indent The amount of spaces to use for indentation
     * @return string The YAML string
     * @throws FormatException If dumping fails
     */
    public static function dump($data, int $inline = 2, int $indent = 4): string
    {
        try {
            $yaml = Yaml::dump($data, $inline, $indent);
            
            self::getLogger()->debug('YAML dumped successfully', [
                'length' => strlen($yaml),
                'inline' => $inline,
                'indent' => $indent
            ]);
            
            return $yaml;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to dump YAML', [
                'error' => $e->getMessage()
            ]);
            throw new FormatException('Failed to dump YAML: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate YAML string
     *
     * @param string $yaml The YAML string to validate
     * @return bool Whether the YAML is valid
     */
    public static function isValid(string $yaml): bool
    {
        try {
            self::parse($yaml);
            self::getLogger()->debug('YAML validation successful', [
                'length' => strlen($yaml)
            ]);
            return true;
        } catch (FormatException $e) {
            self::getLogger()->debug('YAML validation failed', [
                'error' => $e->getMessage(),
                'yaml_preview' => self::sanitizeForLogging($yaml)
            ]);
            return false;
        }
    }

    /**
     * Format YAML string with proper indentation
     *
     * @param string $yaml The YAML string to format
     * @param int $inline The level where you switch to inline YAML
     * @param int $indent The amount of spaces to use for indentation
     * @return string The formatted YAML string
     * @throws FormatException If formatting fails
     */
    public static function format(string $yaml, int $inline = 2, int $indent = 4): string
    {
        try {
            $data = self::parse($yaml);
            $formatted = self::dump($data, $inline, $indent);
            
            self::getLogger()->debug('YAML formatted successfully', [
                'original_length' => strlen($yaml),
                'formatted_length' => strlen($formatted),
                'inline' => $inline,
                'indent' => $indent
            ]);
            
            return $formatted;
        } catch (FormatException $e) {
            self::getLogger()->error('Failed to format YAML', [
                'error' => $e->getMessage(),
                'yaml_preview' => self::sanitizeForLogging($yaml)
            ]);
            throw new FormatException('Failed to format YAML: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sanitize YAML string for logging
     *
     * @param string $yaml The YAML string to sanitize
     * @return string The sanitized YAML string
     */
    private static function sanitizeForLogging(string $yaml): string
    {
        if (strlen($yaml) > self::MAX_YAML_LOG_LENGTH) {
            return substr($yaml, 0, self::MAX_YAML_LOG_LENGTH) . '...';
        }
        return $yaml;
    }

    /**
     * Extract YAML front matter from content
     *
     * @param string $content The content to extract front matter from
     * @return array{frontMatter: array|null, content: string} The extracted front matter and remaining content
     */
    public static function extractFrontMatter(string $content): array
    {
        $result = [
            'frontMatter' => null,
            'content' => $content
        ];

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $content, $matches)) {
            try {
                $result['frontMatter'] = self::parse($matches[1]);
                $result['content'] = $matches[2];
            } catch (FormatException $e) {
                // If front matter parsing fails, return the original content
                $result['frontMatter'] = null;
                $result['content'] = $content;
            }
        }

        return $result;
    }

    /**
     * Add YAML front matter to content
     *
     * @param string $content The content to add front matter to
     * @param array $frontMatter The front matter to add
     * @return string The content with front matter
     * @throws FormatException If front matter encoding fails
     */
    public static function addFrontMatter(string $content, array $frontMatter): string
    {
        try {
            $yaml = self::dump($frontMatter);
            return "---\n$yaml---\n$content";
        } catch (FormatException $e) {
            throw new FormatException('Failed to add front matter: ' . $e->getMessage());
        }
    }
} 
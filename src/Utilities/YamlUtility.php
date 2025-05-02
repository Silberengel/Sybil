<?php
/**
 * Class YamlUtility
 * 
 * This class provides utility functions for working with YAML:
 * - Parsing YAML content
 * - Extracting metadata from YAML
 * - Creating YAML content
 * - Loading and saving YAML files
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\YamlUtility;
 * 
 * // Parse a YAML string
 * $data = YamlUtility::parse($yamlString);
 * 
 * // Extract YAML content from a string
 * $yamlContent = YamlUtility::extractYamlContent($content);
 * 
 * // Create YAML content from an array
 * $yamlString = YamlUtility::createYaml($data);
 * 
 * // Extract metadata from YAML content
 * $metadata = YamlUtility::extractMetadata($yamlContent);
 * 
 * // Load a YAML file
 * $data = YamlUtility::loadFile('path/to/file.yml');
 * 
 * // Save data to a YAML file
 * YamlUtility::saveFile('path/to/file.yml', $data);
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see TagUtility For tag-related operations
 */

namespace Sybil\Utilities;

class YamlUtility
{
    /**
     * Parses a YAML string.
     *
     * @param string $yamlString The YAML string to parse
     * @return array|false The parsed YAML data, or false on failure
     */
    public static function parse(string $yamlString): array|false
    {
        return yaml_parse($yamlString);
    }
    
    /**
     * Extracts YAML content from a string enclosed in YAML tags.
     *
     * @param string $content The content containing YAML tags
     * @param string $startTag The start tag (default: <<YAML>>)
     * @param string $endTag The end tag (default: <</YAML>>)
     * @return string The extracted YAML content
     */
    public static function extractYamlContent(string $content, string $startTag = '<<YAML>>', string $endTag = '<</YAML>>'): string
    {
        $yamlContent = '';
        
        // First check for YAML content within comment markers
        $commentPattern = '/\/\/\/\/\s*\n' . preg_quote($startTag, '/') . '(.*?)' . preg_quote($endTag, '/') . '\s*\n\/\/\/\//s';
        if (preg_match($commentPattern, $content, $matches)) {
            return trim($matches[1]);
        }
        
        // If not found in comments, look for YAML content without comment markers
        $startPos = strpos($content, $startTag);
        $endPos = strpos($content, $endTag, $startPos);
        
        if ($startPos !== false && $endPos !== false) {
            // Extract the YAML content
            $startPos += strlen($startTag);
            $yamlContent = substr($content, $startPos, $endPos - $startPos);
        }
        
        return trim($yamlContent);
    }
    
    /**
     * Creates YAML content from an array.
     *
     * @param array $data The data to convert to YAML
     * @return string|false The YAML content, or false on failure
     */
    public static function createYaml(array $data): string|false
    {
        return yaml_emit($data);
    }
    
    /**
     * Extracts metadata from YAML content.
     *
     * @param string $yamlContent The YAML content
     * @return array The extracted metadata
     */
    public static function extractMetadata(string $yamlContent): array
    {
        // Initialize result array
        $result = [
            'title' => '',
            'author' => '',
            'version' => '',
            'tag-type' => '',
            'auto-update' => '',
            'tags' => []
        ];
        
        // Parse the YAML content
        $parsedYaml = self::parse($yamlContent);
        
        // Check if parsing was successful
        if ($parsedYaml === false) {
            return $result;
        }
        
        // Extract basic metadata
        if (isset($parsedYaml['title'])) {
            $result['title'] = $parsedYaml['title'];
        }
        
        if (isset($parsedYaml['author'])) {
            $result['author'] = $parsedYaml['author'];
        }
        
        if (isset($parsedYaml['version'])) {
            $result['version'] = $parsedYaml['version'];
        }
        
        if (isset($parsedYaml['tag-type'])) {
            $result['tag-type'] = $parsedYaml['tag-type'];
        }
        
        if (isset($parsedYaml['auto-update'])) {
            $result['auto-update'] = $parsedYaml['auto-update'];
        }
        
        // Extract tags
        if (isset($parsedYaml['tags']) && is_array($parsedYaml['tags'])) {
            foreach ($parsedYaml['tags'] as $tag) {
                if (is_array($tag) && count($tag) >= 2) {
                    $result['tags'][] = $tag;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Loads a YAML file.
     *
     * @param string $filePath The path to the YAML file
     * @return array|false The parsed YAML data, or false on failure
     */
    public static function loadFile(string $filePath): array|false
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $yamlContent = file_get_contents($filePath);
        
        if ($yamlContent === false) {
            return false;
        }
        
        return self::parse($yamlContent);
    }
    
    /**
     * Saves data to a YAML file.
     *
     * @param string $filePath The path to the YAML file
     * @param array $data The data to save
     * @return bool True if successful, false otherwise
     */
    public static function saveFile(string $filePath, array $data): bool
    {
        $yamlContent = self::createYaml($data);
        
        if ($yamlContent === false) {
            return false;
        }
        
        return file_put_contents($filePath, $yamlContent) !== false;
    }
}

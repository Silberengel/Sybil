<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for utilities
 */
final class UtilitiesTest extends TestCase
{
    /**
     * Default relay list from HelperFunctions.php
     */
    private array $defaultRelays = [
        'wss://thecitadel.nostr1.com',
        'wss://relay.damus.io',
        'wss://relay.nostr.band',
        'wss://nostr.einundzwanzig.space',
        'wss://relay.primal.net',
        'wss://nos.lol',
        'wss://relay.lumina.rocks',
        'wss://freelay.sovbit.host',
        'wss://wheat.happytavern.co',
        'wss://nostr21.com',
        'wss://theforest.nostr1.com'
    ];

    /**
     * Test the availability of all default relays
     * 
     * This test checks each relay in the default list for availability
     * and prints out which ones are active and which are inactive.
     * It also provides recommendations for which relays to keep in the default list.
     */
    public function testDefaultRelayAvailability(): void
    {
        echo PHP_EOL . "Testing default relay availability:" . PHP_EOL;
        
        $activeRelays = [];
        $inactiveRelays = [];
        $responseTimeMs = [];
        
        foreach ($this->defaultRelays as $relayUrl) {
            echo "Testing relay: $relayUrl... ";
            
            $startTime = microtime(true);
            $isAvailable = $this->checkRelayAvailability($relayUrl);
            $endTime = microtime(true);
            $timeMs = round(($endTime - $startTime) * 1000);
            
            if ($isAvailable) {
                echo "ACTIVE ($timeMs ms)" . PHP_EOL;
                $activeRelays[] = $relayUrl;
                $responseTimeMs[$relayUrl] = $timeMs;
            } else {
                echo "INACTIVE" . PHP_EOL;
                $inactiveRelays[] = $relayUrl;
            }
        }
        
        // Sort active relays by response time (fastest first)
        asort($responseTimeMs);
        $sortedActiveRelays = array_keys($responseTimeMs);
        
        // Print summary
        echo PHP_EOL . "Relay Availability Summary:" . PHP_EOL;
        echo "Active Relays (" . count($activeRelays) . "/" . count($this->defaultRelays) . "):" . PHP_EOL;
        foreach ($sortedActiveRelays as $relay) {
            echo "  - $relay (" . $responseTimeMs[$relay] . " ms)" . PHP_EOL;
        }
        
        echo "Inactive Relays (" . count($inactiveRelays) . "/" . count($this->defaultRelays) . "):" . PHP_EOL;
        foreach ($inactiveRelays as $relay) {
            echo "  - $relay" . PHP_EOL;
        }
        
        // Ensure at least one relay is active
        $this->assertGreaterThan(0, count($activeRelays), 'At least one relay should be active');
        
        // Provide recommendations
        if (count($inactiveRelays) > 0) {
            echo PHP_EOL . "Recommendations:" . PHP_EOL;
            echo "1. Consider removing these inactive relays from the default list in HelperFunctions.php:" . PHP_EOL;
            foreach ($inactiveRelays as $relay) {
                echo "  - $relay" . PHP_EOL;
            }
        }
        
        // Recommend the fastest relays
        echo PHP_EOL . "2. Recommended relay order based on response time (fastest first):" . PHP_EOL;
        $count = 1;
        foreach ($sortedActiveRelays as $relay) {
            echo "  $count. $relay (" . $responseTimeMs[$relay] . " ms)" . PHP_EOL;
            $count++;
        }
        
        // Generate code snippet for updating HelperFunctions.php
        echo PHP_EOL . "3. Code snippet for updating HelperFunctions.php with active relays:" . PHP_EOL;
        echo "```php" . PHP_EOL;
        echo '$relays = [' . PHP_EOL;
        $lastIndex = count($sortedActiveRelays) - 1;
        foreach ($sortedActiveRelays as $index => $relay) {
            $comma = $index < $lastIndex ? ',' : '';
            echo '    new Relay(\'' . $relay . '\')' . $comma . PHP_EOL;
        }
        echo '];' . PHP_EOL;
        echo "```" . PHP_EOL;
        
        // Also provide a recommendation for the top 5 fastest relays
        echo PHP_EOL . "4. Recommended top 5 fastest relays:" . PHP_EOL;
        echo "```php" . PHP_EOL;
        echo '$relays = [' . PHP_EOL;
        $count = 0;
        $top5 = array_slice($sortedActiveRelays, 0, 5);
        $lastIndex = count($top5) - 1;
        foreach ($top5 as $index => $relay) {
            $comma = $index < $lastIndex ? ',' : '';
            echo '    new Relay(\'' . $relay . '\')' . $comma . PHP_EOL;
        }
        echo '];' . PHP_EOL;
        echo "```" . PHP_EOL;
        
        // Provide a ready-to-use code block for HelperFunctions.php
        echo PHP_EOL . "5. Ready-to-use code block for HelperFunctions.php:" . PHP_EOL;
        echo "```php" . PHP_EOL;
        echo 'if (empty($relays)) {' . PHP_EOL;
        echo '    $relays = [' . PHP_EOL;
        $top5 = array_slice($sortedActiveRelays, 0, 5);
        $lastIndex = count($top5) - 1;
        foreach ($top5 as $index => $relay) {
            $comma = $index < $lastIndex ? ',' : '';
            echo '        new Relay(\'' . $relay . '\')' . $comma . PHP_EOL;
        }
        echo '    ];' . PHP_EOL;
        echo '}' . PHP_EOL;
        echo "```" . PHP_EOL;
    }
    
    /**
     * Test sending a kind 1 text note to each relay in the custom relays list
     * 
     * This test sends a simple text note to each relay in the custom relays list
     * from relays.yml and verifies that the note was published successfully.
     */
    public function testSendTextNoteToCustomRelays(): void
    {
        // Skip this test in CI environments or when running quick tests
        if (getenv('CI') === 'true' || getenv('SKIP_NETWORK_TESTS') === 'true') {
            $this->markTestSkipped('Skipping network tests in CI environment');
        }

        // Include necessary files
        require_once __DIR__ . '/MockHelperFunctions.php';
        require_once __DIR__ . '/MockUtilities.php'; // Use the mock Utilities class
        require_once dirname(__DIR__) . '/BaseEvent.php';
        require_once dirname(__DIR__) . '/Tag.php';
        require_once dirname(__DIR__) . '/TextNoteEvent.php';
        
        // Get the custom relays from relays.yml
        $relaysFile = dirname(__DIR__, 2) . "/user/relays.yml";
        $relayUrls = [];
        
        if (file_exists($relaysFile)) {
            $relayUrls = file($relaysFile, FILE_IGNORE_NEW_LINES);
        }
        
        // If no custom relays are defined, use the default relay
        if (empty($relayUrls)) {
            $relayUrls = ['wss://thecitadel.nostr1.com'];
        }
        
        echo PHP_EOL . "Testing sending text notes to custom relays:" . PHP_EOL;
        
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($relayUrls as $relayUrl) {
            echo "Testing relay: $relayUrl... ";
            
            try {
                // Check if the relay is available
                $isAvailable = $this->checkRelayAvailability($relayUrl);
                
                if (!$isAvailable) {
                    echo "SKIPPED (relay not available)" . PHP_EOL;
                    continue;
                }
                
                // Create a text note with a test message
                $textNote = new TextNoteEvent("Test of sending a kind 1 note to the relay " . $relayUrl);
                
                // Publish the note to the relay
                $result = $textNote->publishToRelay($relayUrl);
                
                // Check if the note was published successfully
                if (isset($result['event_id']) && !empty($result['event_id'])) {
                    echo "SUCCESS (event ID: " . $result['event_id'] . ")" . PHP_EOL;
                    $successCount++;
                } else {
                    echo "FAILED (no event ID returned)" . PHP_EOL;
                    $failureCount++;
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . PHP_EOL;
                $failureCount++;
            }
        }
        
        // Print summary
        echo PHP_EOL . "Text Note Publishing Summary:" . PHP_EOL;
        echo "Successful: $successCount" . PHP_EOL;
        echo "Failed: $failureCount" . PHP_EOL;
        
        // Ensure at least one note was published successfully
        $this->assertGreaterThan(0, $successCount, 'At least one text note should be published successfully');
    }

    /**
     * Check if a relay is available by attempting to establish a connection
     * 
     * @param string $relayUrl The WebSocket URL of the relay
     * @return bool True if the relay is available, false otherwise
     */
    private function checkRelayAvailability(string $relayUrl): bool
    {
        try {
            // Parse the URL to get the host and port
            $parsedUrl = parse_url($relayUrl);
            if (!$parsedUrl || !isset($parsedUrl['host'])) {
                return false;
            }
            
            $host = $parsedUrl['host'];
            $port = isset($parsedUrl['port']) ? $parsedUrl['port'] : 443; // Default to 443 for WSS
            
            // Try to establish a TCP connection to the host
            $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 3);
            
            if ($socket) {
                fclose($socket);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            // If any exception occurs, the relay is considered unavailable
            return false;
        }
    }
}

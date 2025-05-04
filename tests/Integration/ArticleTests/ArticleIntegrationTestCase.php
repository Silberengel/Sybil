<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\ArticleTests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sybil\Utility\Log\LoggerFactory;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Base test class for Sybil article integration tests
 */
abstract class ArticleIntegrationTestCase extends TestCase
{
    protected string $relay;
    protected array $eventIds = [];
    protected LoggerInterface $logger;
    protected ConsoleOutput $output;
    protected string $testDataDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->eventIds = [];
        $this->setupLogger();
        $this->testDataDir = dirname(__DIR__) . "/Fixtures";
        $this->setupRelay();
        $this->assertRelayIsRunning();
    }
    
    protected function setupLogger(): void
    {
        $this->output = new ConsoleOutput();
        $this->logger = LoggerFactory::createLogger($this->output);
    }

    protected function setupRelay(): void
    {
        $relaysConfig = require dirname(__DIR__, 2) . '/config/relays.php';
        $this->relay = $relaysConfig['local'] ?? $relaysConfig['default'];
        $this->logger->debug('Using relay', ['relay' => $this->relay]);
    }
    
    protected function executeCommand(string $command): string
    {
        $this->logger->debug('Executing command', ['command' => $command]);
        $output = shell_exec(command: $command . ' 2>&1');
        $this->logger->debug('Command output', ['output' => $output]);
        return $output;
    }
    
    protected function extractEventId(string $output, string $pattern = '/Published.*event with ID: ([a-f0-9]{64})/'): string
    {
        if (preg_match($pattern, $output, $matches)) {
            return $matches[1];
        }
        throw new \RuntimeException('Could not extract event ID from output: ' . $output);
    }
    
    protected function assertRelayIsRunning(): void
    {
        $return = $this->executeCommand('sybil note "Testing relay connection" ' . $this->relay);
        if (strpos($return, 'Event successfully sent') === false) {
            $this->markTestSkipped('Local relay is not running. Please start the relay at ' . $this->relay);
        }
    }
    
    protected function cleanupEvents(): void
    {
        foreach ($this->eventIds as $eventId) {
            $this->logger->info('Cleaning up event', ['eventId' => $eventId]);
            $this->executeCommand('sybil delete ' . $eventId . ' ' . $this->relay);
        }
    }
    
    protected function tearDown(): void
    {
        $this->cleanupEvents();
        parent::tearDown();
    }

    protected function processAndVerifyFile(string $command, string $file, int $expectedEventId, array $metadata, array $sectionTitles): string
    {
        $return = $this->executeCommand($command . ' ' . $file . ' ' . $this->relay);
        
        // Verify the main event
        $this->assertStringContainsString('Published ' . $expectedEventId . ' event with ID', $return);
        
        $eventId = $this->extractEventId($return, '/Published ' . $expectedEventId . ' event with ID: ([a-f0-9]{64})/');
        $this->eventIds[] = $eventId;
        
        // Verify that the main event has the correct kind and metadata
        $mainEvent = $this->executeCommand('sybil fetch ' . $eventId . ' ' . $this->relay);
        $this->assertStringContainsString('"kind": ' . $expectedEventId, $mainEvent);
        
        // Verify metadata is correctly used
        $this->assertEventMetadata($mainEvent, $metadata);
        
        return $eventId;
    }

    protected function assertEventMetadata(string $event, array $expectedMetadata): void
    {
        foreach ($expectedMetadata as $key => $value) {
            $this->assertStringContainsString('"' . $key . '": "' . $value . '"', $event);
        }
    }
} 
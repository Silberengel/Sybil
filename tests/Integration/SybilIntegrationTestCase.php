<?php declare(strict_types=1);

namespace Sybil\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Utility\Relay\RelayAuth;
use Sybil\Utility\Relay\RelayAuthHTTP;
use Sybil\Exception\RelayAuthException;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

/**
 * Base test class for Sybil integration tests
 */
abstract class SybilIntegrationTestCase extends TestCase
{
    protected LoggerInterface $logger;
    protected string $testPrivateKey;
    protected string $testPublicKey;
    protected RelayAuth $wsAuth;
    protected RelayAuthHTTP $httpAuth;
    protected string $testDataDir;
    protected array $testRelays = [
        'ws' => [
            'ws://localhost:8080'
        ],
        'http' => [
            'wss://realy.mleku.dev'
        ]
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up logger
        $this->logger = new Logger('sybil_integration_test');
        $this->logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
        $this->logger->pushHandler(new RotatingFileHandler(
            __DIR__ . '/../../logs/integration_test.log',
            5,
            Logger::DEBUG
        ));
        
        // Set up test data directory
        $this->testDataDir = __DIR__ . '/../data';
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0755, true);
        }
        
        // Generate test keys
        $this->testPrivateKey = KeyUtility::generatePrivateKey();
        $this->testPublicKey = KeyUtility::getPublicKey($this->testPrivateKey);
        
        // Initialize auth handlers
        $this->wsAuth = new RelayAuth($this->logger, $this->testPrivateKey);
        $this->httpAuth = new RelayAuthHTTP($this->logger, $this->testPrivateKey);
        
        $this->logger->info('Test environment initialized', [
            'pubkey' => $this->testPublicKey,
            'test_data_dir' => $this->testDataDir
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->logger->info('Test environment cleaned up');
    }

    /**
     * Assert that an exception has the expected error code
     */
    protected function assertRelayAuthException(
        callable $callback,
        int $expectedCode,
        string $message = ''
    ): void {
        try {
            $callback();
            $this->fail('Expected RelayAuthException was not thrown');
        } catch (RelayAuthException $e) {
            $this->assertEquals(
                $expectedCode,
                $e->getCode(),
                $message ?: 'Unexpected error code in RelayAuthException'
            );
        }
    }

    /**
     * Get a test relay URL
     */
    protected function getTestRelay(string $type = 'ws'): string
    {
        return $this->testRelays[$type][array_rand($this->testRelays[$type])];
    }

    /**
     * Create a test file with the given content
     */
    protected function createTestFile(string $filename, string $content): string
    {
        $filepath = $this->testDataDir . '/' . $filename;
        file_put_contents($filepath, $content);
        $this->logger->debug('Created test file', ['file' => $filepath]);
        return $filepath;
    }

    /**
     * Clean up test files
     */
    protected function cleanupTestFiles(): void
    {
        $files = glob($this->testDataDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->logger->debug('Cleaned up test files');
    }

    /**
     * Execute a command and return its output
     */
    protected function executeCommand(string $command): array
    {
        $this->logger->debug('Executing command', ['command' => $command]);
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        $result = [
            'output' => $output,
            'return_code' => $returnCode
        ];
        
        $this->logger->debug('Command executed', $result);
        
        return $result;
    }

    /**
     * Assert that a command executed successfully
     */
    protected function assertCommandSuccess(array $result, string $message = ''): void
    {
        $this->assertEquals(
            0,
            $result['return_code'],
            $message ?: 'Command failed with return code ' . $result['return_code']
        );
    }

    /**
     * Assert that a command failed with the expected return code
     */
    protected function assertCommandFailure(
        array $result,
        int $expectedCode,
        string $message = ''
    ): void {
        $this->assertEquals(
            $expectedCode,
            $result['return_code'],
            $message ?: 'Command did not fail with expected return code'
        );
    }
} 
<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Integration tests for utility commands
 */
class UtilityIntegrationTest extends CoreIntegrationTestCase
{
    /**
     * Test version command
     */
    public function testVersion(): void
    {
        $this->logger->info('Testing version command');

        $command = 'sybil version';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Sybil version', $output);
        $this->assertStringContainsString('PHP version', $output);
    }

    /**
     * Test help command
     */
    public function testHelp(): void
    {
        $this->logger->info('Testing help command');

        // Test general help
        $command = 'sybil help';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Available commands:', $output);

        // Test specific command help
        $command = 'sybil help note';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('Arguments:', $output);
        $this->assertStringContainsString('Options:', $output);
    }

    /**
     * Test completion command
     */
    public function testCompletion(): void
    {
        $this->logger->info('Testing completion command');

        // Test bash completion
        $command = 'sybil completion bash';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('_sybil_completion()', $output);

        // Test zsh completion
        $command = 'sybil completion zsh';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('_sybil()', $output);
    }

    /**
     * Test convert command
     */
    public function testConvert(): void
    {
        $this->logger->info('Testing convert command');

        // Test hex to bech32 conversion
        $command = 'sybil convert hex-to-bech32 0123456789abcdef';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Bech32:', $output);

        // Test bech32 to hex conversion
        $command = 'sybil convert bech32-to-hex npub1abcdef';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Hex:', $output);
    }

    /**
     * Test validation of utility commands
     */
    public function testUtilityValidation(): void
    {
        $this->logger->info('Testing utility command validation');

        // Test invalid completion shell
        $command = 'sybil completion invalid-shell';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid shell type', $output);

        // Test invalid conversion type
        $command = 'sybil convert invalid-type test';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid conversion type', $output);

        // Test invalid hex format
        $command = 'sybil convert hex-to-bech32 invalid-hex';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid hex format', $output);

        // Test invalid bech32 format
        $command = 'sybil convert bech32-to-hex invalid-bech32';
        $output = $this->executeCommand($command);
        $this->assertStringContainsString('Invalid bech32 format', $output);
    }
} 
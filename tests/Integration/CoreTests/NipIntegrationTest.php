<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\CoreTests;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Tests for NIP information functionality
 */
final class NipIntegrationTest extends CoreIntegrationTestCase
{
    public function testNipInfoCommand(): void
    {
        try {
            $this->logger->info('Testing NIP info command');
            
            // Test getting info for NIP-01
            $output = $this->executeCommand('php bin/sybil nip info 1');
            $this->assertStringContainsString('Basic protocol flow description', $output, 'Should contain NIP-01 description');
            $this->assertStringContainsString('NIP-01', $output, 'Should contain NIP-01 identifier');
            
            // Test getting info for NKBIP-01
            $output = $this->executeCommand('php bin/sybil nip info 30000');
            $this->assertStringContainsString('Key Block', $output, 'Should contain NKBIP-01 description');
            $this->assertStringContainsString('NKBIP-01', $output, 'Should contain NKBIP-01 identifier');
            
            $this->logger->info('Successfully tested NIP info command');
        } catch (\Exception $e) {
            $this->logger->error('Failed to test NIP info command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test NIP info command: ' . $e->getMessage());
        }
    }

    public function testNipListCommand(): void
    {
        try {
            $this->logger->info('Testing NIP list command');
            
            $output = $this->executeCommand('php bin/sybil nip list');
            
            // Verify standard NIPs are listed
            $this->assertStringContainsString('NIP-01', $output, 'Should list NIP-01');
            $this->assertStringContainsString('NIP-02', $output, 'Should list NIP-02');
            
            // Verify NKBIPs are listed
            $this->assertStringContainsString('NKBIP-01', $output, 'Should list NKBIP-01');
            
            $this->logger->info('Successfully tested NIP list command');
        } catch (\Exception $e) {
            $this->logger->error('Failed to test NIP list command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test NIP list command: ' . $e->getMessage());
        }
    }

    public function testNipSearchCommand(): void
    {
        try {
            $this->logger->info('Testing NIP search command');
            
            // Search for NIPs related to events
            $output = $this->executeCommand('php bin/sybil nip search event');
            $this->assertStringContainsString('NIP-01', $output, 'Should find NIP-01 in search results');
            
            // Search for NIPs related to keys
            $output = $this->executeCommand('php bin/sybil nip search key');
            $this->assertStringContainsString('NKBIP-01', $output, 'Should find NKBIP-01 in search results');
            
            $this->logger->info('Successfully tested NIP search command');
        } catch (\Exception $e) {
            $this->logger->error('Failed to test NIP search command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test NIP search command: ' . $e->getMessage());
        }
    }

    public function testInvalidNipNumber(): void
    {
        try {
            $this->logger->info('Testing invalid NIP number');
            
            $output = $this->executeCommand('php bin/sybil nip info 999999');
            $this->assertStringContainsString('NIP not found', $output, 'Should handle invalid NIP number');
            
            $this->logger->info('Successfully tested invalid NIP number');
        } catch (\Exception $e) {
            $this->logger->error('Failed to test invalid NIP number', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test invalid NIP number: ' . $e->getMessage());
        }
    }

    public function testNipDependencies(): void
    {
        try {
            $this->logger->info('Testing NIP dependencies');
            
            // Test getting dependencies for NIP-01
            $output = $this->executeCommand('php bin/sybil nip deps 1');
            $this->assertStringContainsString('Dependencies', $output, 'Should show dependencies section');
            
            // Test getting dependencies for NKBIP-01
            $output = $this->executeCommand('php bin/sybil nip deps 30000');
            $this->assertStringContainsString('Dependencies', $output, 'Should show dependencies section');
            
            $this->logger->info('Successfully tested NIP dependencies');
        } catch (\Exception $e) {
            $this->logger->error('Failed to test NIP dependencies', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail('Failed to test NIP dependencies: ' . $e->getMessage());
        }
    }
} 
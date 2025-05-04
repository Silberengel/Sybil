<?php declare(strict_types=1);

namespace Sybil\Tests\Integration\ArticleTests;

use Sybil\Tests\Integration\ArticleTests\ArticleIntegrationTestCase;
use Sybil\Exception\RelayAuthException;
use Sybil\Exception\EventCreationException;
use Psr\Log\LogLevel;

/**
 * Tests for article error handling functionality
 */
final class ArticleErrorHandlingTest extends ArticleIntegrationTestCase
{
    public function testInvalidFilePath(): void
    {
        try {
            $this->logger->info('Testing invalid file path handling');
            $invalidFile = $this->testDataDir . "/nonexistent_file.md";
            $return = $this->executeCommand('longform ' . $invalidFile . ' ' . $this->relay);
            
            $this->assertStringContainsString('File not found', $return);
            $this->logger->info('Successfully tested invalid file path handling');
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for invalid file path test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to test invalid file path handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to test invalid file path handling: " . $e->getMessage());
        }
    }

    public function testInvalidFileContent(): void
    {
        try {
            $this->logger->info('Testing invalid file content handling');
            $invalidFile = $this->testDataDir . "/invalid_content.md";
            $return = $this->executeCommand('longform ' . $invalidFile . ' ' . $this->relay);
            
            $this->assertStringContainsString('Invalid file content', $return);
            $this->logger->info('Successfully tested invalid file content handling');
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for invalid file content test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to test invalid file content handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to test invalid file content handling: " . $e->getMessage());
        }
    }

    public function testInvalidMetadata(): void
    {
        try {
            $this->logger->info('Testing invalid metadata handling');
            $testFile = $this->testDataDir . "/invalid_metadata.md";
            $return = $this->executeCommand('longform ' . $testFile . ' ' . $this->relay);
            
            $this->assertStringContainsString('Invalid metadata', $return);
            $this->logger->info('Successfully tested invalid metadata handling');
        } catch (RelayAuthException $e) {
            $this->logger->error('Authentication failed for invalid metadata test', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->fail("Authentication failed: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Failed to test invalid metadata handling', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail("Failed to test invalid metadata handling: " . $e->getMessage());
        }
    }
} 
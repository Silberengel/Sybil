<?php

namespace Sybil\Command\Trait;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sybil\Exception\CommandException;

/**
 * Trait providing common command functionality
 */
trait CommandTrait
{
    protected LoggerInterface $logger;

    /**
     * Execute a command with proper error handling and logging
     */
    protected function executeWithErrorHandling(
        InputInterface $input,
        OutputInterface $output,
        callable $callback
    ): int {
        try {
            $this->logger->info('Starting command execution', [
                'command' => $this->getName(),
                'arguments' => $this->getInputArguments($input),
                'options' => $this->getInputOptions($input)
            ]);

            $result = $callback($input, $output);

            $this->logger->info('Command execution completed', [
                'command' => $this->getName(),
                'result' => $result
            ]);

            return $result;
        } catch (CommandException $e) {
            $this->handleCommandException($e, $output);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->handleUnexpectedException($e, $output);
            return Command::FAILURE;
        }
    }

    /**
     * Get input arguments as array
     */
    protected function getInputArguments(InputInterface $input): array
    {
        $arguments = [];
        foreach ($input->getArguments() as $name => $value) {
            if ($name !== 'command') {
                $arguments[$name] = $value;
            }
        }
        return $arguments;
    }

    /**
     * Get input options as array
     */
    protected function getInputOptions(InputInterface $input): array
    {
        return $input->getOptions();
    }

    /**
     * Handle command-specific exceptions
     */
    protected function handleCommandException(CommandException $e, OutputInterface $output): void
    {
        $this->logger->error('Command execution failed', [
            'command' => $this->getName(),
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $e->getContext()
        ]);

        $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
    }

    /**
     * Handle unexpected exceptions
     */
    protected function handleUnexpectedException(\Exception $e, OutputInterface $output): void
    {
        $this->logger->error('Unexpected error during command execution', [
            'command' => $this->getName(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        $output->writeln(sprintf('<error>Unexpected error: %s</error>', $e->getMessage()));
    }

    /**
     * Validate required input
     */
    protected function validateRequiredInput(InputInterface $input, array $required): void
    {
        foreach ($required as $name) {
            if (!$input->getArgument($name)) {
                throw new CommandException(
                    sprintf('Missing required argument: %s', $name),
                    CommandException::MISSING_ARGUMENT,
                    ['argument' => $name]
                );
            }
        }
    }

    /**
     * Format duration in seconds to human-readable format
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%.2fms', $seconds * 1000);
        }
        return sprintf('%.2fs', $seconds);
    }
} 
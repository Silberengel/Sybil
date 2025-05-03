<?php

namespace Sybil\Command;

/**
 * Interface for commands
 * 
 * This interface defines the methods that all commands must implement.
 * Commands are the primary way users interact with the Sybil application.
 * Each command represents a specific action that can be performed.
 * 
 * @package Sybil\Command
 * 
 * @example
 * ```php
 * class MyCommand implements CommandInterface
 * {
 *     public function getName(): string
 *     {
 *         return 'my-command';
 *     }
 *     
 *     public function getDescription(): string
 *     {
 *         return 'Performs a specific action';
 *     }
 *     
 *     public function execute(array $args): int
 *     {
 *         // Command implementation
 *         return 0; // Success
 *     }
 * }
 * ```
 */
interface CommandInterface
{
    /**
     * Get the command name
     *
     * The command name is used to identify the command in the CLI.
     * It should be lowercase and use hyphens for word separation.
     * 
     * @return string The command name (e.g., 'my-command')
     */
    public function getName(): string;
    
    /**
     * Get the command description
     *
     * The description should be a brief, one-line explanation of what the command does.
     * It is displayed in the help output and should be clear and concise.
     * 
     * @return string The command description
     */
    public function getDescription(): string;
    
    /**
     * Execute the command
     *
     * This method is called when the command is invoked from the CLI.
     * It should handle all command logic, including argument validation,
     * error handling, and logging.
     * 
     * @param array $args Command arguments (excluding the command name)
     * @return int Exit code (0 for success, non-zero for failure)
     */
    public function execute(array $args): int;
}

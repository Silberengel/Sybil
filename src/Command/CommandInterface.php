<?php

namespace Sybil\Command;

/**
 * Interface for commands
 * 
 * This interface defines the methods that all commands must implement.
 */
interface CommandInterface
{
    /**
     * Get the command name
     *
     * @return string The command name
     */
    public function getName(): string;
    
    /**
     * Get the command description
     *
     * @return string The command description
     */
    public function getDescription(): string;
    
    /**
     * Execute the command
     *
     * @param array $args Command arguments
     * @return int Exit code
     */
    public function execute(array $args): int;
}

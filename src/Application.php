<?php

namespace Sybil;

use Sybil\Command\CommandInterface;
use InvalidArgumentException;

/**
 * Main application class
 * 
 * This class is the main entry point for the application.
 * It handles command registration and execution.
 */
class Application
{
    /**
     * @var array Registered commands
     */
    private array $commands = [];
    
    /**
     * @var array Registered services
     */
    private array $services = [];
    
    /**
     * Register a command
     *
     * @param CommandInterface $command The command to register
     * @return self
     */
    public function registerCommand(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }
    
    /**
     * Get all registered commands
     *
     * @return array Array of registered commands
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
    
    /**
     * Register a service
     *
     * @param string $name The service name
     * @param callable $factory The service factory
     * @return self
     */
    public function register(string $name, callable $factory): self
    {
        $this->services[$name] = $factory;
        return $this;
    }
    
    /**
     * Get a service
     *
     * @param string $name The service name
     * @return mixed The service
     * @throws InvalidArgumentException If the service is not registered
     */
    public function get(string $name): mixed
    {
        if (!isset($this->services[$name])) {
            throw new InvalidArgumentException("Service '$name' is not registered.");
        }
        
        // Lazy-load the service
        if (is_callable($this->services[$name])) {
            $this->services[$name] = ($this->services[$name])($this);
        }
        
        return $this->services[$name];
    }
    
    /**
     * Run the application
     *
     * @param array $argv Command line arguments
     * @return int Exit code
     */
    public function run(array $argv): int
    {
        // Remove the script name from the arguments
        array_shift($argv);
        
        // If no command is provided or help command is explicitly called
        if (empty($argv) || $argv[0] === 'help') {
            // If help command is registered, use it
            if (isset($this->commands['help'])) {
                $args = empty($argv) ? [] : array_slice($argv, 1);
                return $this->commands['help']->execute($args);
            }
            // Otherwise, fall back to the built-in help
            else {
                return $this->showBuiltinHelp($argv[1] ?? null);
            }
        }
        
        // Get the command name
        $commandName = $argv[0];
        
        // Check if the command exists
        if (!isset($this->commands[$commandName])) {
            echo "Error: Command '$commandName' not found." . PHP_EOL;
            return 1;
        }
        
        // Remove the command name from the arguments
        array_shift($argv);
        
        // Execute the command
        try {
            return $this->commands[$commandName]->execute($argv);
        } catch (InvalidArgumentException $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
            return 1;
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . PHP_EOL;
            return 1;
        }
    }
    
    /**
     * Show the built-in help message (fallback if HelpCommand is not registered)
     *
     * @param string|null $commandName Optional command name to show help for
     * @return int Exit code
     */
    private function showBuiltinHelp(?string $commandName = null): int
    {
        // If a command name is provided, show help for that command
        if ($commandName !== null) {
            // Check if the command exists
            if (!isset($this->commands[$commandName])) {
                echo "Error: Command '$commandName' not found." . PHP_EOL;
                return 1;
            }
            
            // Show help for the command
            echo "Usage: sybil $commandName [arguments]" . PHP_EOL;
            echo PHP_EOL;
            echo $this->commands[$commandName]->getDescription() . PHP_EOL;
            echo PHP_EOL;
            echo "For more detailed help, register the HelpCommand." . PHP_EOL;
            
            return 0;
        }
        
        // Show general help
        echo "Sybil - A tool for creating and publishing Nostr events" . PHP_EOL;
        echo PHP_EOL;
        echo "Usage: sybil <command> [arguments]" . PHP_EOL;
        echo PHP_EOL;
        echo "Available commands:" . PHP_EOL;
        
        // Sort commands by name
        $commands = $this->commands;
        ksort($commands);
        
        // Show command list
        foreach ($commands as $name => $command) {
            echo "  $name" . str_repeat(' ', max(0, 15 - strlen($name))) . $command->getDescription() . PHP_EOL;
        }
        
        echo PHP_EOL;
        echo "For detailed help on a specific command, use: sybil help <command>" . PHP_EOL;
        
        return 0;
    }
}

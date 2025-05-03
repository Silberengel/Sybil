<?php

namespace Sybil;

use Sybil\Command\CommandInterface;
use Sybil\Service\LoggerService;
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
     * @var array<string, CommandInterface> Registered commands
     */
    private array $commands = [];
    
    /**
     * @var array<string, callable|mixed> Registered services
     */
    private array $services = [];
    
    /**
     * @var LoggerService|null The logger instance
     */
    private ?LoggerService $logger = null;
    
    /**
     * Get the logger instance
     *
     * @return LoggerService The logger instance
     */
    private function getLogger(): LoggerService
    {
        if ($this->logger === null) {
            try {
                $this->logger = $this->get('logger');
            } catch (InvalidArgumentException $e) {
                $this->logger = new LoggerService(true);
            }
        }
        return $this->logger;
    }
    
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
     * @return array<string, CommandInterface> Array of registered commands
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
     * @template T
     * @param string $name The service name
     * @return T The service
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
            $this->getLogger()->error("Command '$commandName' not found.");
            return 1;
        }
        
        // Remove the command name from the arguments
        array_shift($argv);
        
        // Execute the command
        try {
            return $this->commands[$commandName]->execute($argv);
        } catch (InvalidArgumentException $e) {
            $this->getLogger()->error($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getMessage());
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
                $this->getLogger()->error("Command '$commandName' not found.");
                return 1;
            }
            
            // Show help for the command
            $this->getLogger()->output("Usage: sybil $commandName [arguments]");
            $this->getLogger()->output("");
            $this->getLogger()->output($this->commands[$commandName]->getDescription());
            $this->getLogger()->output("");
            $this->getLogger()->output("For more detailed help, register the HelpCommand.");
            
            return 0;
        }
        
        // Show general help
        $this->getLogger()->output("Sybil - A tool for creating and publishing Nostr events");
        $this->getLogger()->output("");
        $this->getLogger()->output("Usage: sybil <command> [arguments]");
        $this->getLogger()->output("");
        $this->getLogger()->output("Available commands:");
        
        // Sort commands by name
        $commands = $this->commands;
        ksort($commands);
        
        // Show command list
        foreach ($commands as $name => $command) {
            $this->getLogger()->output("  $name" . str_repeat(' ', max(0, 15 - strlen($name))) . $command->getDescription());
        }
        
        $this->getLogger()->output("");
        $this->getLogger()->output("For detailed help on a specific command, use: sybil help <command>");
        
        return 0;
    }
}

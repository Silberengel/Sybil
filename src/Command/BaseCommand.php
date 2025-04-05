<?php

namespace Sybil\Command;

use Sybil\Application;
use InvalidArgumentException;

/**
 * Base class for commands
 * 
 * This class implements the CommandInterface and provides common functionality
 * for all commands.
 */
abstract class BaseCommand implements CommandInterface
{
    /**
     * @var string Command name
     */
    protected string $name = '';
    
    /**
     * @var string Command description
     */
    protected string $description = '';
    
    /**
     * @var Application Application instance
     */
    protected Application $app;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }
    
    /**
     * Get the command name
     *
     * @return string The command name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the command description
     *
     * @return string The command description
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    /**
     * Validate command arguments
     *
     * @param array $args Command arguments
     * @param int $minArgs Minimum number of arguments
     * @param string $errorMessage Error message to display if validation fails
     * @return bool True if validation passes, false otherwise
     */
    protected function validateArgs(array $args, int $minArgs, string $errorMessage): bool
    {
        if (count($args) < $minArgs) {
            echo "Error: $errorMessage" . PHP_EOL;
            echo "Usage: sybil " . $this->getName() . " [arguments]" . PHP_EOL;
            return false;
        }
        
        return true;
    }
}

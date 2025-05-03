<?php

namespace Sybil\Command;

use Sybil\Application;
use Sybil\Service\LoggerService;
use Sybil\Command\Traits\RelayOptionTrait;
use Sybil\Command\Traits\KeyOptionTrait;
use InvalidArgumentException;
use Exception;

/**
 * Base class for commands
 * 
 * This class implements the CommandInterface and provides common functionality
 * for all commands.
 */
abstract class BaseCommand implements CommandInterface
{
    use RelayOptionTrait;
    use KeyOptionTrait;
    
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
     * @var LoggerService Logger service
     */
    protected LoggerService $logger;
    
    /**
     * Constructor
     *
     * @param Application $app The application instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        try {
            $this->logger = $app->get('logger');
        } catch (InvalidArgumentException $e) {
            // If logger service isn't available yet, create a new instance
            $this->logger = new LoggerService(true);
        }
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
     * Execute the command
     *
     * @param array $args Command arguments
     * @return int Exit code
     */
    abstract public function execute(array $args): int;
    
    /**
     * Handle the result of an operation
     *
     * @param array $result The operation result
     * @param string $successMessage The message to display on success
     * @param string $failureMessage The message to display on failure
     * @return bool Whether the operation was successful
     */
    protected function handleResult(array $result, string $successMessage, string $failureMessage): bool
    {
        if (isset($result['success']) && $result['success']) {
            $this->logger->info($successMessage);
            if (!empty($result['successful_relays'])) {
                $this->logger->info("  Published to: " . implode(", ", $result['successful_relays']));
            }
            if (!empty($result['failed_relays'])) {
                $this->logger->warning("  Failed to publish to: " . implode(", ", $result['failed_relays']));
            }
            return true;
        } else {
            $this->logger->warning($failureMessage);
            if (isset($result['message'])) {
                $this->logger->warning("  Error: " . $result['message']);
            }
            return false;
        }
    }
    
    /**
     * Execute a command with standard error handling
     *
     * @param callable $operation The operation to execute
     * @param array $args The command arguments
     * @return int Exit code
     */
    protected function executeWithErrorHandling(callable $operation, array $args): int
    {
        try {
            return $operation($args);
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());
            return 1;
        } catch (Exception $e) {
            $this->logger->error("An error occurred: " . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Validate required arguments
     *
     * @param array $args The command arguments
     * @param int $requiredCount The number of required arguments
     * @param string $errorMessage The error message to display if validation fails
     * @return bool Whether the arguments are valid
     */
    protected function validateRequiredArgs(array $args, int $requiredCount, string $errorMessage): bool
    {
        if (count($args) < $requiredCount) {
            $this->logger->error($errorMessage);
            return false;
        }
        return true;
    }
    
    /**
     * Log the start of an operation
     *
     * @param string $operation The operation being performed
     * @param string|null $relayUrl Optional relay URL
     */
    protected function logOperationStart(string $operation, ?string $relayUrl = null): void
    {
        if ($relayUrl) {
            $this->logger->info("{$operation} to specific relay: {$relayUrl}");
        } else {
            $this->logger->info("{$operation} to all configured relays");
        }
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
            $this->logger->error($errorMessage);
            $this->logger->output("Usage: sybil " . $this->getName() . " [arguments]");
            return false;
        }
        
        return true;
    }
}

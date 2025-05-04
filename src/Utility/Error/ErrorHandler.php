<?php

namespace Sybil\Utility\Error;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sybil\Exception\SybilException;

/**
 * Error handler for Sybil
 * 
 * This class provides error handling functionality for the application,
 * supporting both logging and user-friendly error output.
 */
class ErrorHandler
{
    /**
     * @var LoggerInterface Logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * @var OutputInterface Console output instance
     */
    private OutputInterface $output;
    
    /**
     * @var bool Whether debug mode is enabled
     */
    private bool $debug;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger Logger instance
     * @param OutputInterface $output Console output instance
     * @param bool $debug Whether debug mode is enabled
     */
    public function __construct(
        LoggerInterface $logger,
        OutputInterface $output,
        bool $debug = false
    ) {
        $this->logger = $logger;
        $this->output = $output;
        $this->debug = $debug;
    }

    /**
     * Handle an error
     *
     * @param \Throwable $error The error to handle
     * @param bool|null $debug Override debug mode
     * @return void
     */
    public function handle(\Throwable $error, ?bool $debug = null): void
    {
        $debug = $debug ?? $this->debug;
        
        // Log the error
        $this->logError($error);
        
        // Output error message
        if ($debug) {
            $this->outputDebugError($error);
        } else {
            $this->outputUserError($error);
        }
    }

    /**
     * Log an error
     *
     * @param \Throwable $error The error to log
     * @return void
     */
    private function logError(\Throwable $error): void
    {
        $context = [
            'exception' => $error,
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString()
        ];
        
        // Add previous exception if exists
        if ($error->getPrevious()) {
            $context['previous'] = $error->getPrevious();
        }
        
        // Log with appropriate level
        if ($error instanceof SybilException) {
            $this->logger->log(
                $this->getLogLevel($error->getSeverity()),
                $error->getMessage(),
                $context
            );
        } else {
            $this->logger->error($error->getMessage(), $context);
        }
    }

    /**
     * Output a debug error message
     *
     * @param \Throwable $error The error to output
     * @return void
     */
    private function outputDebugError(\Throwable $error): void
    {
        $this->output->writeln('');
        $this->output->writeln('<error>Error Details:</error>');
        $this->output->writeln('');
        
        // Output error message
        $this->output->writeln(sprintf(
            '<error>%s</error>',
            $error->getMessage()
        ));
        
        // Output error type
        $this->output->writeln(sprintf(
            '<comment>Type:</comment> %s',
            get_class($error)
        ));
        
        // Output error code if not 0
        if ($error->getCode() !== 0) {
            $this->output->writeln(sprintf(
                '<comment>Code:</comment> %s',
                $error->getCode()
            ));
        }
        
        // Output file and line
        $this->output->writeln(sprintf(
            '<comment>File:</comment> %s:%d',
            $error->getFile(),
            $error->getLine()
        ));
        
        // Output stack trace
        $this->output->writeln('');
        $this->output->writeln('<comment>Stack Trace:</comment>');
        $this->output->writeln($error->getTraceAsString());
        
        // Output previous exception if exists
        if ($error->getPrevious()) {
            $this->output->writeln('');
            $this->output->writeln('<comment>Previous Exception:</comment>');
            $this->outputDebugError($error->getPrevious());
        }
        
        $this->output->writeln('');
    }

    /**
     * Output a user-friendly error message
     *
     * @param \Throwable $error The error to output
     * @return void
     */
    private function outputUserError(\Throwable $error): void
    {
        $message = $error->getMessage();
        
        // For SybilException, use a more user-friendly message
        if ($error instanceof SybilException) {
            $message = $this->formatUserMessage($error);
        }
        
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '<error>Error: %s</error>',
            $message
        ));
        
        // Add hint for debug mode
        $this->output->writeln('');
        $this->output->writeln('<comment>Tip: Run with SYBIL_DEBUG=1 for more details</comment>');
        $this->output->writeln('');
    }

    /**
     * Format a user-friendly message for a SybilException
     *
     * @param SybilException $error The error to format
     * @return string Formatted message
     */
    private function formatUserMessage(SybilException $error): string
    {
        $message = $error->getMessage();
        
        // Add context information if available
        $context = $error->getContext();
        if (!empty($context)) {
            $message .= ' (' . implode(', ', array_map(
                fn($key, $value) => "$key: $value",
                array_keys($context),
                array_values($context)
            )) . ')';
        }
        
        return $message;
    }

    /**
     * Get the log level for a severity
     *
     * @param string $severity The severity level
     * @return string The log level
     */
    private function getLogLevel(string $severity): string
    {
        return match ($severity) {
            SybilException::SEVERITY_EMERGENCY => 'emergency',
            SybilException::SEVERITY_ALERT     => 'alert',
            SybilException::SEVERITY_CRITICAL  => 'critical',
            SybilException::SEVERITY_ERROR     => 'error',
            SybilException::SEVERITY_WARNING   => 'warning',
            SybilException::SEVERITY_NOTICE    => 'notice',
            SybilException::SEVERITY_INFO      => 'info',
            default                            => 'error'
        };
    }

    /**
     * Format an error message
     *
     * @param string $message The error message
     * @param array $context Additional context
     * @return string The formatted error message
     */
    public function formatError(string $message, array $context = []): string
    {
        $formatted = $message;

        // Add context if available
        if (!empty($context)) {
            $contextStr = [];
            foreach ($context as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $contextStr[] = "$key: $value";
            }
            $formatted .= ' (' . implode(', ', $contextStr) . ')';
        }

        return $formatted;
    }

    /**
     * Check if an error is fatal
     *
     * @param \Throwable $error The error to check
     * @return bool Whether the error is fatal
     */
    public function isFatalError(\Throwable $error): bool
    {
        return $error instanceof \Error ||
               $error instanceof \ParseError ||
               $error instanceof \TypeError;
    }

    /**
     * Get error severity level
     *
     * @param \Throwable $error The error to check
     * @return string The severity level
     */
    public function getSeverityLevel(\Throwable $error): string
    {
        if ($this->isFatalError($error)) {
            return 'FATAL';
        }

        if ($error instanceof \ErrorException) {
            switch ($error->getSeverity()) {
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    return 'ERROR';
                case E_WARNING:
                case E_CORE_WARNING:
                case E_COMPILE_WARNING:
                case E_USER_WARNING:
                    return 'WARNING';
                case E_NOTICE:
                case E_USER_NOTICE:
                    return 'NOTICE';
                default:
                    return 'INFO';
            }
        }

        return 'ERROR';
    }
} 
# Troubleshooting Guide

This guide helps you resolve common issues and understand error messages in Sybil.

## Error Types

### Exception Classes

Sybil uses several exception classes to handle different types of errors:

1. **Core Exceptions**
   ```php
   Sybil\Core\Exception\ApplicationException
   Sybil\Core\Exception\ConfigurationException
   Sybil\Core\Exception\ServiceException
   ```

2. **Command Exceptions**
   ```php
   Sybil\Command\Exception\CommandException
   Sybil\Command\Exception\InvalidCommandException
   Sybil\Command\Exception\CommandExecutionException
   ```

3. **Relay Exceptions**
   ```php
   Sybil\Relay\Exception\RelayException
   Sybil\Relay\Exception\ConnectionException
   Sybil\Relay\Exception\AuthenticationException
   Sybil\Relay\Exception\SubscriptionException
   ```

4. **Event Exceptions**
   ```php
   Sybil\Event\Exception\EventException
   Sybil\Event\Exception\InvalidEventException
   Sybil\Event\Exception\EventValidationException
   ```

5. **Git Exceptions**
   ```php
   Sybil\Git\Exception\GitException
   Sybil\Git\Exception\RepositoryException
   Sybil\Git\Exception\VersionException
   ```

### Error Codes

Sybil uses numeric error codes for different types of errors:

```php
// Core Errors (1000-1999)
const ERROR_CONFIGURATION = 1000;
const ERROR_SERVICE = 1001;
const ERROR_APPLICATION = 1002;

// Command Errors (2000-2999)
const ERROR_COMMAND = 2000;
const ERROR_INVALID_COMMAND = 2001;
const ERROR_COMMAND_EXECUTION = 2002;

// Relay Errors (3000-3999)
const ERROR_RELAY = 3000;
const ERROR_CONNECTION = 3001;
const ERROR_AUTHENTICATION = 3002;
const ERROR_SUBSCRIPTION = 3003;

// Event Errors (4000-4999)
const ERROR_EVENT = 4000;
const ERROR_INVALID_EVENT = 4001;
const ERROR_EVENT_VALIDATION = 4002;

// Git Errors (5000-5999)
const ERROR_GIT = 5000;
const ERROR_REPOSITORY = 5001;
const ERROR_VERSION = 5002;
```

## Common Issues

### 1. Installation Issues

#### PHP Version Mismatch
```
Error: PHP version 8.1 or higher is required
Solution: Install PHP 8.1 or higher
```

#### Composer Dependencies
```
Error: Failed to install dependencies
Solution: Run 'composer install' with --verbose flag
```

#### Permission Issues
```
Error: Permission denied
Solution: Check file permissions and ownership
```

### 2. Configuration Issues

#### Missing Configuration
```
Error: Configuration file not found
Solution: Create config/app.php with required settings
```

#### Invalid Configuration
```
Error: Invalid configuration format
Solution: Check JSON syntax and required fields
```

#### Environment Variables
```
Error: Required environment variable not set
Solution: Set NOSTR_SECRET_KEY environment variable
Note: The public key is automatically derived from the secret key
```

### 3. Relay Issues

#### Connection Errors
```
Error: Failed to connect to relay
Solution: Check relay URL and network connection
```

#### Authentication Errors
```
Error: Authentication failed
Solution: Check relay authentication settings
```

#### Subscription Errors
```
Error: Failed to subscribe to events
Solution: Check subscription parameters
```

### 4. Event Issues

#### Invalid Events
```
Error: Invalid event format
Solution: Check event structure and required fields
```

#### Validation Errors
```
Error: Event validation failed
Solution: Check event content and signatures
```

#### Publishing Errors
```
Error: Failed to publish event
Solution: Check relay connection and permissions
```

### 5. Git Issues

#### Repository Errors
```
Error: Git repository not found
Solution: Initialize git repository
```

#### Version Errors
```
Error: Invalid version format
Solution: Check version format in config
```

#### Sync Errors
```
Error: Failed to sync with Nostr
Solution: Check git and Nostr configurations
```

## Debugging

### 1. Enable Debug Mode

```bash
# Set debug mode in config
export SYBIL_DEBUG=true

# Or use command line option
sybil command --verbose
```

### 2. Check Logs

```bash
# View application logs
tail -f storage/logs/sybil.log

# View error logs
tail -f storage/logs/error.log
```

### 3. Use Verbose Output

```bash
# Enable verbose output for commands
sybil command --verbose

# Show detailed error information
sybil command --verbose
```

## Error Handling

### 1. Try-Catch Blocks

```php
try {
    // Command execution
} catch (RelayException $e) {
    // Handle relay errors
} catch (EventException $e) {
    // Handle event errors
} catch (Exception $e) {
    // Handle other errors
}
```

### 2. Error Reporting

```php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log errors
error_log($message, 3, 'error.log');
```

### 3. Custom Error Handlers

```php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Handle errors
});

set_exception_handler(function($exception) {
    // Handle exceptions
});
```

Return to the [Read Me](./../README.md)
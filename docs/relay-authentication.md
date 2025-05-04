# Relay Authentication

This document describes how to use Sybil's relay authentication system, which implements both NIP-42 (WebSocket) and NIP-98 (HTTP) authentication methods.

## Overview

Sybil provides two main authentication classes:
- `RelayAuth`: Handles NIP-42 WebSocket authentication
- `RelayAuthHTTP`: Handles NIP-98 HTTP authentication

## NIP-42 WebSocket Authentication

### Basic Usage

```php
use Sybil\Utility\Relay\RelayAuth;
use Sybil\Utility\Key\KeyUtility;

// Initialize with a private key (optional, will use environment variable if not provided)
$auth = new RelayAuth($logger, KeyUtility::getPrivateKey());

// Authenticate with a relay
try {
    $auth->authenticate($challenge, 'nip42');
} catch (RelayAuthException $e) {
    // Handle authentication error
}
```

### Supported Authentication Methods

- `nip42`: Standard Nostr authentication (default)
- `nip98`: HTTP authentication (for REST APIs)

### Error Handling

The `RelayAuthException` class provides detailed error codes and messages:

```php
try {
    $auth->authenticate($challenge);
} catch (RelayAuthException $e) {
    switch ($e->getCode()) {
        case RelayAuthException::ERROR_CHALLENGE_MISSING:
            // Handle missing challenge
            break;
        case RelayAuthException::ERROR_SIGNATURE_INVALID:
            // Handle invalid signature
            break;
        // ... handle other error codes
    }
}
```

## NIP-98 HTTP Authentication

### Basic Usage

```php
use Sybil\Utility\Relay\RelayAuthHTTP;
use Sybil\Utility\Key\KeyUtility;

// Initialize with a private key
$auth = new RelayAuthHTTP($logger, KeyUtility::getPrivateKey());

// Create an authentication header for an HTTP request
$authHeader = $auth->createAuthHeader(
    'https://relay.example.com/event',
    'POST',
    $requestBody
);

// Use the header in your HTTP request
$response = $client->post('/event', [
    'headers' => [
        'Authorization' => $authHeader,
        'Content-Type' => 'application/json'
    ],
    'json' => $requestBody
]);
```

### Middleware Usage

The `RelayAuthMiddleware` can be used to protect HTTP endpoints:

```php
use Sybil\Middleware\RelayAuthMiddleware;

// Create middleware instance
$middleware = new RelayAuthMiddleware(
    $logger,
    $auth,
    ['/public', '/health'], // Excluded paths
    ['GET', 'HEAD', 'OPTIONS'] // Excluded methods
);

// Add to your middleware stack
$app->add($middleware);
```

### Configuration Options

The middleware supports several configuration options:

```php
$middleware = new RelayAuthMiddleware(
    $logger,
    $auth,
    [
        // Paths that don't require authentication
        'excluded_paths' => ['/public', '/health'],
        // HTTP methods that don't require authentication
        'excluded_methods' => ['GET', 'HEAD', 'OPTIONS']
    ]
);
```

## Integration Testing

The `RelayAuthTest` class provides an example of how to test the authentication system:

```php
use Sybil\Tests\Integration\RelayAuthTest;

class MyRelayTest extends RelayAuthTest
{
    protected function setUp(): void
    {
        parent::setUp();
        // Add your custom setup
    }

    public function testMyRelayFlow(): void
    {
        // Test authentication, posting, and querying
        $this->testCompleteRelayFlow();
    }
}
```

## Error Codes

### General Errors (1000-1999)
- `ERROR_AUTHENTICATION_FAILED`: General authentication failure
- `ERROR_VERIFICATION_FAILED`: Verification failure
- `ERROR_INVALID_DATA`: Invalid authentication data
- `ERROR_UNSUPPORTED_METHOD`: Unsupported authentication method
- `ERROR_TIMEOUT`: Authentication timeout
- `ERROR_RATE_LIMIT`: Rate limit exceeded

### NIP-42 Errors (2000-2999)
- `ERROR_CHALLENGE_MISSING`: Missing authentication challenge
- `ERROR_CHALLENGE_INVALID`: Invalid challenge
- `ERROR_SIGNATURE_INVALID`: Invalid signature
- `ERROR_PUBKEY_MISMATCH`: Public key mismatch
- `ERROR_AUTH_REJECTED`: Authentication rejected by relay

### NIP-98 Errors (3000-3999)
- `ERROR_EVENT_CREATION_FAILED`: Failed to create auth event
- `ERROR_HEADER_CREATION_FAILED`: Failed to create auth header
- `ERROR_INVALID_EVENT_KIND`: Invalid event kind
- `ERROR_INVALID_TIMESTAMP`: Invalid timestamp
- `ERROR_TIMESTAMP_EXPIRED`: Timestamp expired
- `ERROR_URL_MISMATCH`: URL mismatch
- `ERROR_METHOD_MISMATCH`: Method mismatch
- `ERROR_PAYLOAD_MISMATCH`: Payload hash mismatch
- `ERROR_INVALID_SIGNATURE`: Invalid signature
- `ERROR_MISSING_HEADER`: Missing Authorization header
- `ERROR_INVALID_HEADER_FORMAT`: Invalid header format

### Middleware Errors (4000-4999)
- `ERROR_MIDDLEWARE_CONFIG`: Configuration error
- `ERROR_MIDDLEWARE_INIT`: Initialization error
- `ERROR_MIDDLEWARE_PROCESS`: Processing error
- `ERROR_MIDDLEWARE_RESPONSE`: Response error

## Best Practices

1. Always use proper error handling and logging
2. Implement rate limiting for authentication attempts
3. Use secure key storage (environment variables or secure vault)
4. Validate all input data before authentication
5. Use HTTPS for all HTTP authentication
6. Implement proper session management
7. Monitor authentication failures
8. Keep authentication tokens short-lived
9. Implement proper cleanup of expired sessions
10. Use proper logging levels for different types of events

## Security Considerations

1. Never log sensitive data (private keys, tokens)
2. Implement proper rate limiting
3. Use secure key storage
4. Validate all input data
5. Use HTTPS for all HTTP communication
6. Implement proper session management
7. Monitor authentication failures
8. Keep authentication tokens short-lived
9. Implement proper cleanup
10. Use proper logging levels 
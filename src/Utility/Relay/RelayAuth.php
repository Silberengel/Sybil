<?php

namespace Sybil\Utility\Relay;

use Sybil\Utility\Key\KeyPair;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Utility\Relay\Exception\RelayAuthException;
use Psr\Log\LoggerInterface;

/**
 * Handles authentication with Nostr relays.
 * Implements NIP-42 for client authentication.
 */
class RelayAuth
{
    private LoggerInterface $logger;
    private KeyPair $keyPair;
    private int $maxRetries = 3;
    private int $retryDelay = 1; // seconds
    private array $authMethods = ['nip42']; // Default to NIP-42

    public function __construct(
        LoggerInterface $logger,
        ?string $privateKey = null,
        array $authMethods = ['nip42']
    ) {
        $this->logger = $logger;
        $this->keyPair = new KeyPair($privateKey ?? KeyUtility::getPrivateKey());
        $this->authMethods = $authMethods;
    }

    /**
     * Authenticates with a relay using the given challenge.
     *
     * @param string $challenge The challenge string from the relay
     * @param string $method The authentication method to use (default: nip42)
     * @return array The authentication data
     * @throws RelayAuthException If authentication fails
     */
    public function authenticate(string $challenge, string $method = 'nip42'): array
    {
        if (!in_array($method, $this->authMethods)) {
            throw new RelayAuthException(
                "Unsupported authentication method: $method",
                RelayAuthException::ERROR_UNSUPPORTED_METHOD
            );
        }

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $this->logger->debug('Authenticating with relay', [
                    'challenge' => $challenge,
                    'method' => $method,
                    'attempt' => $attempts + 1
                ]);

                switch ($method) {
                    case 'nip42':
                        return $this->authenticateNip42($challenge);
                    default:
                        throw new RelayAuthException(
                            "Unsupported authentication method: $method",
                            RelayAuthException::ERROR_UNSUPPORTED_METHOD
                        );
                }
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts < $this->maxRetries) {
                    $this->logger->warning('Authentication attempt failed, retrying', [
                        'attempt' => $attempts,
                        'error' => $e->getMessage(),
                        'delay' => $this->retryDelay
                    ]);
                    sleep($this->retryDelay);
                }
            }
        }

        $this->logger->error('Authentication failed after all retries', [
            'attempts' => $attempts,
            'error' => $lastException->getMessage()
        ]);

        throw new RelayAuthException(
            'Authentication failed after ' . $attempts . ' attempts: ' . $lastException->getMessage(),
            RelayAuthException::ERROR_AUTHENTICATION_FAILED,
            $lastException
        );
    }

    /**
     * Authenticate using NIP-42
     *
     * @param string $challenge The challenge string
     * @return array The authentication data
     * @throws RelayAuthException If authentication fails
     */
    private function authenticateNip42(string $challenge): array
    {
        try {
            // Sign the challenge with our private key
            $signature = $this->keyPair->sign($challenge);
            
            // Create authentication data
            $authData = [
                'type' => 'auth',
                'pubkey' => $this->keyPair->getPublicKey(),
                'challenge' => $challenge,
                'signature' => $signature
            ];

            $this->logger->info('NIP-42 authentication successful', [
                'pubkey' => $this->keyPair->getPublicKey()
            ]);

            return $authData;
        } catch (\Exception $e) {
            throw new RelayAuthException(
                'NIP-42 authentication failed: ' . $e->getMessage(),
                RelayAuthException::ERROR_AUTHENTICATION_FAILED,
                $e
            );
        }
    }

    /**
     * Verifies authentication data from a relay.
     *
     * @param array $data The authentication data from the relay
     * @param string $challenge The original challenge string
     * @param string $method The authentication method used
     * @return bool True if authentication is valid
     * @throws RelayAuthException If verification fails
     */
    public function verify(array $data, string $challenge, string $method = 'nip42'): bool
    {
        try {
            $this->logger->debug('Verifying authentication data', [
                'challenge' => $challenge,
                'method' => $method
            ]);

            if (!isset($data['pubkey']) || !isset($data['signature'])) {
                throw new RelayAuthException(
                    'Invalid authentication data: missing required fields',
                    RelayAuthException::ERROR_INVALID_DATA
                );
            }

            switch ($method) {
                case 'nip42':
                    return $this->verifyNip42($data, $challenge);
                default:
                    throw new RelayAuthException(
                        "Unsupported authentication method: $method",
                        RelayAuthException::ERROR_UNSUPPORTED_METHOD
                    );
            }
        } catch (\Exception $e) {
            $this->logger->error('Authentication verification failed', [
                'error' => $e->getMessage()
            ]);
            throw new RelayAuthException(
                'Failed to verify authentication: ' . $e->getMessage(),
                RelayAuthException::ERROR_VERIFICATION_FAILED,
                $e
            );
        }
    }

    /**
     * Verify NIP-42 authentication
     *
     * @param array $data The authentication data
     * @param string $challenge The challenge string
     * @return bool True if authentication is valid
     */
    private function verifyNip42(array $data, string $challenge): bool
    {
        // Verify the signature
        $isValid = KeyPair::verify(
            $challenge,
            $data['signature'],
            $data['pubkey']
        );

        if (!$isValid) {
            $this->logger->warning('NIP-42 authentication verification failed', [
                'pubkey' => $data['pubkey']
            ]);
            return false;
        }

        $this->logger->info('NIP-42 authentication verification successful', [
            'pubkey' => $data['pubkey']
        ]);

        return true;
    }

    /**
     * Get the public key used for authentication
     *
     * @return string The public key in hex format
     */
    public function getPublicKey(): string
    {
        return $this->keyPair->getPublicKey();
    }

    /**
     * Get the public key in npub format
     *
     * @return string The public key in npub format
     */
    public function getNpub(): string
    {
        return $this->keyPair->getNpub();
    }

    /**
     * Set the maximum number of retry attempts
     *
     * @param int $maxRetries The maximum number of retries
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = max(1, $maxRetries);
    }

    /**
     * Set the delay between retry attempts
     *
     * @param int $retryDelay The delay in seconds
     */
    public function setRetryDelay(int $retryDelay): void
    {
        $this->retryDelay = max(1, $retryDelay);
    }

    /**
     * Set the supported authentication methods
     *
     * @param array $methods The supported methods
     */
    public function setAuthMethods(array $methods): void
    {
        $this->authMethods = $methods;
    }
} 
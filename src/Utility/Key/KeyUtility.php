<?php

namespace Sybil\Utility\Key;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Sybil\Exception\KeyException;
use Sybil\Utility\Log\LoggerFactory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use swentel\nostr\Key\Key as NostrKey;

/**
 * Utility class for working with Nostr keys
 * 
 * This class provides utility functions for working with Nostr keys,
 * including key management, validation, and conversion.
 */
class KeyUtility
{
    private static ?string $defaultEnvVar = null;
    private static ?LoggerInterface $logger = null;
    private const MAX_KEY_LOG_LENGTH = 8;

    private static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createLogger('key_utility');
        }
        return self::$logger;
    }

    /**
     * Initialize the default environment variable name
     * 
     * @param ParameterBagInterface $params The parameter bag containing configuration
     */
    public static function initialize(ParameterBagInterface $params): void
    {
        self::$defaultEnvVar = $params->get('app.nostr_secret_key_env');
        self::getLogger()->debug('Key utility initialized', [
            'env_var' => self::$defaultEnvVar
        ]);
    }

    /**
     * Get the private key from an environment variable
     * 
     * @param string|null $envVar The environment variable name (defaults to configured value)
     * @return string The private key in hex format
     * @throws KeyException If the key is not found or invalid
     */
    public static function getPrivateKey(?string $envVar = null): string
    {
        try {
            $envVar = $envVar ?? self::$defaultEnvVar;
            if ($envVar === null) {
                throw new KeyException("Default environment variable not configured");
            }

            $key = getenv($envVar);
            
            if ($key === false) {
                throw new KeyException("Environment variable $envVar not found");
            }

            $nostrKey = new NostrKey($key);
            $privateKey = $nostrKey->getPrivateKey();

            self::getLogger()->debug('Retrieved private key', [
                'env_var' => $envVar,
                'key_length' => strlen($privateKey)
            ]);

            return $privateKey;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get private key', [
                'env_var' => $envVar,
                'error' => $e->getMessage()
            ]);
            throw new KeyException("Failed to get private key: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the private key in nsec format from an environment variable
     * 
     * @param string|null $envVar The environment variable name (defaults to configured value)
     * @return string The private key in nsec format
     * @throws KeyException If the key is not found or invalid
     */
    public static function getNsec(?string $envVar = null): string
    {
        try {
            $envVar = $envVar ?? self::$defaultEnvVar;
            if ($envVar === null) {
                throw new KeyException("Default environment variable not configured");
            }

            $key = getenv($envVar);
            
            if ($key === false) {
                throw new KeyException("Environment variable $envVar not found");
            }

            $nostrKey = new NostrKey($key);
            $nsec = $nostrKey->getNsec();

            self::getLogger()->debug('Retrieved nsec', [
                'env_var' => $envVar,
                'nsec_length' => strlen($nsec)
            ]);

            return $nsec;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get nsec', [
                'env_var' => $envVar,
                'error' => $e->getMessage()
            ]);
            throw new KeyException("Failed to get nsec: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the public key from an environment variable
     * 
     * @param string|null $envVar The environment variable name (defaults to configured value)
     * @return string The public key in hex format
     * @throws KeyException If the key is not found or invalid
     */
    public static function getPublicKey(?string $envVar = null): string
    {
        try {
            $envVar = $envVar ?? self::$defaultEnvVar;
            if ($envVar === null) {
                throw new KeyException("Default environment variable not configured");
            }

            $key = getenv($envVar);
            
            if ($key === false) {
                throw new KeyException("Environment variable $envVar not found");
            }

            $nostrKey = new NostrKey($key);
            $publicKey = $nostrKey->getPublicKey();

            self::getLogger()->debug('Retrieved public key', [
                'env_var' => $envVar,
                'public_key_length' => strlen($publicKey)
            ]);

            return $publicKey;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get public key', [
                'env_var' => $envVar,
                'error' => $e->getMessage()
            ]);
            throw new KeyException("Failed to get public key: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the public key in npub format from an environment variable
     * 
     * @param string|null $envVar The environment variable name (defaults to configured value)
     * @return string The public key in npub format
     * @throws KeyException If the key is not found or invalid
     */
    public static function getNpub(?string $envVar = null): string
    {
        try {
            $envVar = $envVar ?? self::$defaultEnvVar;
            if ($envVar === null) {
                throw new KeyException("Default environment variable not configured");
            }

            $key = getenv($envVar);
            
            if ($key === false) {
                throw new KeyException("Environment variable $envVar not found");
            }

            $nostrKey = new NostrKey($key);
            $npub = $nostrKey->getNpub();

            self::getLogger()->debug('Retrieved npub', [
                'env_var' => $envVar,
                'npub_length' => strlen($npub)
            ]);

            return $npub;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get npub', [
                'env_var' => $envVar,
                'error' => $e->getMessage()
            ]);
            throw new KeyException("Failed to get npub: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate a new keypair
     * 
     * @return array{privateKey: string, publicKey: string, nsec: string, npub: string} The generated keypair
     * @throws KeyException If key generation fails
     */
    public static function generateKeyPair(): array
    {
        try {
            $nostrKey = new NostrKey();
            $keyPair = [
                'privateKey' => $nostrKey->getPrivateKey(),
                'publicKey' => $nostrKey->getPublicKey(),
                'nsec' => $nostrKey->getNsec(),
                'npub' => $nostrKey->getNpub()
            ];

            self::getLogger()->debug('Generated new key pair', [
                'public_key_length' => strlen($keyPair['publicKey']),
                'nsec_length' => strlen($keyPair['nsec']),
                'npub_length' => strlen($keyPair['npub'])
            ]);

            return $keyPair;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to generate key pair', [
                'error' => $e->getMessage()
            ]);
            throw new KeyException("Failed to generate key pair: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate a private key
     * 
     * @param string $key The private key to validate (hex or nsec format)
     * @return bool True if the key is valid
     */
    public static function isValidPrivateKey(string $key): bool
    {
        try {
            new NostrKey($key);
            self::getLogger()->debug('Private key validation successful', [
                'key_length' => strlen($key)
            ]);
            return true;
        } catch (\Exception $e) {
            self::getLogger()->debug('Private key validation failed', [
                'key_length' => strlen($key),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate a public key
     * 
     * @param string $key The public key to validate (hex or npub format)
     * @return bool True if the key is valid
     */
    public static function isValidPublicKey(string $key): bool
    {
        try {
            if (str_starts_with($key, 'npub')) {
                NostrKey::npubToHex($key);
            } else {
                // Basic hex validation
                if (!preg_match('/^[0-9a-f]{64}$/', $key)) {
                    self::getLogger()->debug('Public key validation failed: invalid hex format', [
                        'key_length' => strlen($key)
                    ]);
                    return false;
                }
            }
            self::getLogger()->debug('Public key validation successful', [
                'key_length' => strlen($key)
            ]);
            return true;
        } catch (\Exception $e) {
            self::getLogger()->debug('Public key validation failed', [
                'key_length' => strlen($key),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 
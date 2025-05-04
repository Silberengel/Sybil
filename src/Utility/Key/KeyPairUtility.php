<?php

namespace Sybil\Utility\Key;

use Psr\Log\LoggerInterface;
use Sybil\Exception\KeyException;
use Sybil\Utility\Log\LoggerFactory;
use Sybil\Utility\Format\JsonUtility;
use Sybil\Utility\Format\YamlUtility;
use swentel\nostr\Key\Key as NostrKey;
use InvalidArgumentException;

/**
 * Utility class for working with Nostr keypairs
 * 
 * This class provides utility functions for working with Nostr keypairs,
 * including generation, validation, and conversion.
 */
class KeyPairUtility
{
    private static ?LoggerInterface $logger = null;

    private static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createLogger('key_pair_utility');
        }
        return self::$logger;
    }

    private NostrKey $key;

    /**
     * Create a new KeyPair instance
     * 
     * @param string|null $privateKey Optional private key (hex or nsec format)
     * @throws InvalidArgumentException If the private key is invalid
     */
    public function __construct(?string $privateKey = null)
    {
        if ($privateKey === null) {
            $this->key = new NostrKey();
        } else {
            try {
                $this->key = new NostrKey($privateKey);
            } catch (\Exception $e) {
                throw new InvalidArgumentException('Invalid private key: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get the private key in hex format
     * 
     * @return string The private key in hex format
     */
    public function getPrivateKey(): string
    {
        return $this->key->getPrivateKey();
    }

    /**
     * Get the private key in nsec format
     * 
     * @return string The private key in nsec format
     */
    public function getNsec(): string
    {
        return $this->key->getNsec();
    }

    /**
     * Get the public key in hex format
     * 
     * @return string The public key in hex format
     */
    public function getPublicKey(): string
    {
        return $this->key->getPublicKey();
    }

    /**
     * Get the public key in npub format
     * 
     * @return string The public key in npub format
     */
    public function getNpub(): string
    {
        return $this->key->getNpub();
    }

    /**
     * Sign a message with the private key
     * 
     * @param string $message The message to sign
     * @return string The signature in hex format
     */
    public function sign(string $message): string
    {
        return $this->key->sign($message);
    }

    /**
     * Verify a signature
     * 
     * @param string $message The message that was signed
     * @param string $signature The signature to verify
     * @param string $publicKey The public key to verify against
     * @return bool True if the signature is valid
     */
    public static function verify(string $message, string $signature, string $publicKey): bool
    {
        return NostrKey::verify($message, $signature, $publicKey);
    }

    /**
     * Convert a hex private key to nsec format
     * 
     * @param string $hexKey The private key in hex format
     * @return string The private key in nsec format
     * @throws InvalidArgumentException If the hex key is invalid
     */
    public static function hexToNsec(string $hexKey): string
    {
        try {
            return NostrKey::hexToNsec($hexKey);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid hex key: ' . $e->getMessage());
        }
    }

    /**
     * Convert an nsec private key to hex format
     * 
     * @param string $nsecKey The private key in nsec format
     * @return string The private key in hex format
     * @throws InvalidArgumentException If the nsec key is invalid
     */
    public static function nsecToHex(string $nsecKey): string
    {
        try {
            return NostrKey::nsecToHex($nsecKey);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid nsec key: ' . $e->getMessage());
        }
    }

    /**
     * Convert a hex public key to npub format
     * 
     * @param string $hexKey The public key in hex format
     * @return string The public key in npub format
     * @throws InvalidArgumentException If the hex key is invalid
     */
    public static function hexToNpub(string $hexKey): string
    {
        try {
            return NostrKey::hexToNpub($hexKey);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid hex key: ' . $e->getMessage());
        }
    }

    /**
     * Convert an npub public key to hex format
     * 
     * @param string $npubKey The public key in npub format
     * @return string The public key in hex format
     * @throws InvalidArgumentException If the npub key is invalid
     */
    public static function npubToHex(string $npubKey): string
    {
        try {
            return NostrKey::npubToHex($npubKey);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid npub key: ' . $e->getMessage());
        }
    }

    /**
     * Generate a new key pair
     *
     * @return array{privateKey: string, publicKey: string} The generated key pair
     * @throws KeyException If key generation fails
     */
    public static function generate(): array
    {
        try {
            $key = new NostrKey();
            
            self::getLogger()->debug('Key pair generated successfully', [
                'public_key_length' => strlen($key->getPublicKey())
            ]);

            return [
                'privateKey' => $key->getPrivateKey(),
                'publicKey' => $key->getPublicKey()
            ];
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to generate key pair', [
                'error' => $e->getMessage()
            ]);
            throw new KeyException('Failed to generate key pair: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate a key pair
     *
     * @param string $privateKey The private key
     * @param string $publicKey The public key
     * @return bool Whether the key pair is valid
     */
    public static function validate(string $privateKey, string $publicKey): bool
    {
        try {
            $key = new NostrKey($privateKey);
            $isValid = $key->getPublicKey() === $publicKey;

            self::getLogger()->debug('Key pair validation completed', [
                'is_valid' => $isValid,
                'public_key_length' => strlen($publicKey)
            ]);

            return $isValid;
        } catch (\Exception $e) {
            self::getLogger()->error('Key pair validation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Convert a key pair to JSON
     *
     * @param string $privateKey The private key
     * @param string $publicKey The public key
     * @return string The JSON string
     * @throws KeyException If conversion fails
     */
    public static function toJson(string $privateKey, string $publicKey): string
    {
        try {
            $data = [
                'privateKey' => $privateKey,
                'publicKey' => $publicKey
            ];

            self::getLogger()->debug('Converting key pair to JSON', [
                'public_key_length' => strlen($publicKey)
            ]);

            return JsonUtility::encode($data);
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to convert key pair to JSON', [
                'error' => $e->getMessage()
            ]);
            throw new KeyException('Failed to convert key pair to JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert a key pair to YAML
     *
     * @param string $privateKey The private key
     * @param string $publicKey The public key
     * @return string The YAML string
     * @throws KeyException If conversion fails
     */
    public static function toYaml(string $privateKey, string $publicKey): string
    {
        try {
            $data = [
                'privateKey' => $privateKey,
                'publicKey' => $publicKey
            ];

            self::getLogger()->debug('Converting key pair to YAML', [
                'public_key_length' => strlen($publicKey)
            ]);

            return YamlUtility::dump($data);
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to convert key pair to YAML', [
                'error' => $e->getMessage()
            ]);
            throw new KeyException('Failed to convert key pair to YAML: ' . $e->getMessage(), 0, $e);
        }
    }
} 
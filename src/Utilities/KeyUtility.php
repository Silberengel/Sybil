<?php
/**
 * Class KeyUtility
 * 
 * This class provides utility functions for working with Nostr keys:
 * - Getting and converting between different key formats (nsec, npub, hex)
 * - Handling key operations
 * - Validating key formats
 * 
 * It follows object-oriented principles with clear method responsibilities.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\KeyUtility;
 * 
 * // Get the nsec private key from the environment
 * $nsec = KeyUtility::getNsec();
 * 
 * // Get the npub public key
 * $npub = KeyUtility::getNpub();
 * 
 * // Get the public hex key
 * $publicHex = KeyUtility::getPublicHexKey();
 * 
 * // Convert a hex key to an nsec
 * $nsec = KeyUtility::hexToNsec($hexKey);
 * 
 * // Convert a hex key to an npub
 * $npub = KeyUtility::hexToNpub($hexKey);
 * 
 * // Validate an nsec
 * $isValid = KeyUtility::isValidNsec($nsec);
 * ```
 * 
 * This class is part of the Sybil utilities, which have been organized into smaller,
 * more focused classes to improve maintainability and readability. Each class has a
 * specific responsibility and provides methods related to that responsibility.
 * 
 * @see TagUtility For tag-related operations
 * @see EventPreparationUtility For event preparation operations
 */

namespace Sybil\Utilities;

use swentel\nostr\Key\Key;

class KeyUtility
{
    /**
     * Gets the npub's private key from the environment variable
     * NOSTR_SECRET_KEY. Converts from hex to nsec, where applicable.
     *
     * @return string The nsec
     */
    public static function getNsec(): string
    {
        // Get private nsec key
        $keys = new Key();
        $envKey = getenv('NOSTR_SECRET_KEY');

        // convert hex ID to nsec, if found, then return
        if(!str_starts_with($envKey, 'nsec')){
            return $keys->convertPrivateKeyToBech32($envKey);
        }else{
            return $envKey;
        }
    }

    /**
     * Transforms an nsec to a public hex ID.
     *
     * @return string The hex ID
     */
    public static function getPublicHexKey(): string
    {
        // Get nsec
        $keys = new Key();
        $nsec = self::getNsec();

        // convert nsec to public hex ID and return
        $privateHex = $keys->convertToHex(key: $nsec);
        return $keys->getPublicKey(private_hex: $privateHex);
    }

    /**
     * Transforms an nsec to an npub.
     *
     * @return string The npub
     */
    public static function getNpub(): string
    {
        // Get nsec
        $keys = new Key();
        $nsec = self::getNsec();

        // convert nsec to private hex ID
        $privateHex = $keys->convertToHex(key: $nsec);

        // convert private hex to npub and return
        $publicHex = $keys->getPublicKey($privateHex);
        return $keys->convertPublicKeyToBech32($publicHex);
    }
    
    /**
     * Converts a hex key to an nsec.
     *
     * @param string $hexKey The hex key to convert
     * @return string The nsec
     */
    public static function hexToNsec(string $hexKey): string
    {
        $keys = new Key();
        return $keys->convertPrivateKeyToBech32($hexKey);
    }
    
    /**
     * Converts a hex key to an npub.
     *
     * @param string $hexKey The hex key to convert
     * @return string The npub
     */
    public static function hexToNpub(string $hexKey): string
    {
        $keys = new Key();
        return $keys->convertPublicKeyToBech32($hexKey);
    }
    
    /**
     * Converts an nsec to a hex key.
     *
     * @param string $nsec The nsec to convert
     * @return string The hex key
     */
    public static function nsecToHex(string $nsec): string
    {
        $keys = new Key();
        return $keys->convertToHex(key: $nsec);
    }
    
    /**
     * Converts an npub to a hex key.
     *
     * @param string $npub The npub to convert
     * @return string The hex key
     */
    public static function npubToHex(string $npub): string
    {
        $keys = new Key();
        return $keys->convertToHex(key: $npub);
    }
    
    /**
     * Validates if a string is a valid nsec.
     *
     * @param string $nsec The nsec to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidNsec(string $nsec): bool
    {
        return str_starts_with($nsec, 'nsec') && strlen($nsec) >= 60;
    }
    
    /**
     * Validates if a string is a valid npub.
     *
     * @param string $npub The npub to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidNpub(string $npub): bool
    {
        return str_starts_with($npub, 'npub') && strlen($npub) >= 60;
    }
    
    /**
     * Validates if a string is a valid hex key.
     *
     * @param string $hexKey The hex key to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidHexKey(string $hexKey): bool
    {
        return ctype_xdigit($hexKey) && (strlen($hexKey) === 64);
    }
}

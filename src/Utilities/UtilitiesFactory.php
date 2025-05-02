<?php
/**
 * Class UtilitiesFactory
 * 
 * This class provides a factory for creating utility class instances.
 * It helps with transitioning from the old Utilities class to the new utility classes.
 * 
 * Example usage:
 * ```php
 * use Sybil\Utilities\UtilitiesFactory;
 * 
 * // Create an EventUtility instance
 * $eventUtility = UtilitiesFactory::createEventUtility('event-id');
 * 
 * // Create a TagUtility instance
 * $tagUtility = UtilitiesFactory::createTagUtility();
 * 
 * // Create a KeyUtility instance
 * $keyUtility = UtilitiesFactory::createKeyUtility();
 * 
 * // Create a RelayUtility instance
 * $relayUtility = UtilitiesFactory::createRelayUtility();
 * 
 * // Create a LogUtility instance
 * $logUtility = UtilitiesFactory::createLogUtility();
 * 
 * // Create a YamlUtility instance
 * $yamlUtility = UtilitiesFactory::createYamlUtility();
 * 
 * // Create a RequestUtility instance
 * $requestUtility = UtilitiesFactory::createRequestUtility();
 * 
 * // Create an ErrorHandlingUtility instance
 * $errorHandlingUtility = UtilitiesFactory::createErrorHandlingUtility();
 * 
 * // Create an EventPreparationUtility instance
 * $eventPreparationUtility = UtilitiesFactory::createEventPreparationUtility();
 * ```
 */

namespace Sybil\Utilities;

class UtilitiesFactory
{
    /**
     * Create an EventUtility instance
     *
     * @param string $eventID Optional event ID to initialize with
     * @return EventUtility The created EventUtility instance
     */
    public static function createEventUtility(string $eventID = ''): EventUtility
    {
        return new EventUtility($eventID);
    }
    
    /**
     * Create a TagUtility instance
     *
     * @return TagUtility The created TagUtility instance
     */
    public static function createTagUtility(): TagUtility
    {
        return new TagUtility();
    }
    
    /**
     * Create a KeyUtility instance
     *
     * @return KeyUtility The created KeyUtility instance
     */
    public static function createKeyUtility(): KeyUtility
    {
        return new KeyUtility();
    }
    
    /**
     * Create a RelayUtility instance
     *
     * @return RelayUtility The created RelayUtility instance
     */
    public static function createRelayUtility(): RelayUtility
    {
        return new RelayUtility();
    }
    
    /**
     * Create a LogUtility instance
     *
     * @return LogUtility The created LogUtility instance
     */
    public static function createLogUtility(): LogUtility
    {
        return new LogUtility();
    }
    
    /**
     * Create a YamlUtility instance
     *
     * @return YamlUtility The created YamlUtility instance
     */
    public static function createYamlUtility(): YamlUtility
    {
        return new YamlUtility();
    }
    
    /**
     * Create a RequestUtility instance
     *
     * @return RequestUtility The created RequestUtility instance
     */
    public static function createRequestUtility(): RequestUtility
    {
        return new RequestUtility();
    }
    
    /**
     * Create an ErrorHandlingUtility instance
     *
     * @return ErrorHandlingUtility The created ErrorHandlingUtility instance
     */
    public static function createErrorHandlingUtility(): ErrorHandlingUtility
    {
        return new ErrorHandlingUtility();
    }
    
    /**
     * Create an EventPreparationUtility instance
     *
     * @return EventPreparationUtility The created EventPreparationUtility instance
     */
    public static function createEventPreparationUtility(): EventPreparationUtility
    {
        return new EventPreparationUtility();
    }
}

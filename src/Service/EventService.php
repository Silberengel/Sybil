<?php

namespace Sybil\Service;

use swentel\nostr\Event\Event;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Relay\RelaySet;
use InvalidArgumentException;
use Exception;
use TypeError;
use Sybil\Utilities\Utilities;
/**
 * Service for managing events
 * 
 * This service handles event-related functionality, such as preparing, signing,
 * and sending events to relays.
 */
class EventService
{
    /**
     * @var array Application configuration
     */
    private array $appConfig;
    
    /**
     * @var RelayService Relay service
     */
    private RelayService $relayService;
    
    /**
     * @var LoggerService Logger service
     */
    private LoggerService $logger;
    
    /**
     * Constructor
     *
     * @param array $appConfig Application configuration
     * @param RelayService $relayService Relay service
     * @param LoggerService $logger Logger service
     */
    public function __construct(array $appConfig, RelayService $relayService, LoggerService $logger)
    {
        $this->appConfig = $appConfig;
        $this->relayService = $relayService;
        $this->logger = $logger;
    }
    
    /**
     * Prepare and send an event
     *
     * @param Event $event The event to prepare and send
     * @return array Result of sending the event
     * @throws InvalidArgumentException If the private key is missing or invalid
     */
    public function prepareAndSendEvent(Event $event): array
    {
        // Get private key from environment
        $utility = new Utilities();
        $privateKey = $utility::getNsec();
        
        // Get the event kind
        $kind = 0;
        if (method_exists($event, 'getKind')) {
            $kind = $event->getKind();
        }
        
        // Get relay list for this kind of event
        $relays = $this->relayService->getRelayList($kind);
        
        // Sign the event
        $signer = new Sign();
        $signer->signEvent($event, $privateKey);
        
        // Create event message
        $eventMessage = new EventMessage($event);
        
        // Send the event with retry on failure
        return $this->sendEventWithRetry($eventMessage, $relays);
    }
    
    /**
     * Send an event with retry on failure
     *
     * @param EventMessage $eventMessage The event message to send
     * @param array $customRelays Optional array of Relay objects to use
     * @return array Result of sending the event
     */
    public function sendEventWithRetry(EventMessage $eventMessage, array $customRelays = []): array
    {
        // Get the event kind and ID for better error reporting
        $eventKind = 0;
        $eventId = 'unknown-event-id';
        $eventObj = null;
        
        // Use reflection to access the protected event property
        try {
            $reflection = new \ReflectionClass($eventMessage);
            $properties = $reflection->getProperties();
            
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $value = $property->getValue($eventMessage);
                
                // Check if this property contains the Event object
                if (is_object($value)) {
                    $eventObj = $value;
                    if (method_exists($value, 'getKind')) {
                        $eventKind = $value->getKind();
                    }
                    if (method_exists($value, 'getId')) {
                        $eventId = $value->getId();
                    }
                    break;
                }
            }
        } catch (Exception $e) {
            // If reflection fails, continue with default values
        }
        
        // Debug output is now handled by the LoggerService
        
        // Use custom relays if provided, otherwise use default relays
        $relays = !empty($customRelays) ? $customRelays : $this->relayService->getDefaultRelays($eventKind);
        
        // Log the event kind for better debugging
        $this->logger->info("Sending event kind " . $eventKind . " to " . count($relays) . " relays...");
        
        // For deletion events (kind 5), provide additional information
        if ($eventKind === 5) {
            $this->logger->info("Note: Deletion events (kind 5) may be rejected by some relays due to relay policies.");
            $this->logger->info("      Ensure you're using the same private key that created the original event.");
        }
        
        $relaySet = new RelaySet();
        $relaySet->setRelays($relays);
        $relaySet->setMessage($eventMessage);
        
        $maxRetries = 3;
        $retryCount = 0;
        $successfulRelays = [];
        $failedRelays = [];
        
        while ($retryCount < $maxRetries) {
            try {
                // Use our helper function to handle errors
                $result = $this->executeWithErrorHandling(function() use ($relaySet) {
                    return $relaySet->send();
                }, 'RelaySet.php');
                
                // Check if any relays accepted the event
                $anySuccess = false;
                foreach ($result as $relayUrl => $response) {
                    if (is_object($response) && method_exists($response, 'isSuccess')) {
                        $isSuccess = $response->isSuccess();
                        if ($isSuccess) {
                            $anySuccess = true;
                            $successfulRelays[] = $relayUrl;
                        } else {
                            $failedRelays[] = $relayUrl;
                        }
                    }
                }
                
                if ($anySuccess) {
                    $this->logger->info("Event successfully sent to " . count($successfulRelays) . " relays:");
                    $this->logger->info("  Accepted by: " . implode(", ", $successfulRelays));
                    if (!empty($failedRelays)) {
                        $this->logger->info("  Rejected by: " . implode(", ", $failedRelays));
                    }
                    return [
                        'success' => true,
                        'message' => 'Event sent successfully to ' . count($successfulRelays) . ' relays',
                        'event_id' => $eventId,
                        'successful_relays' => $successfulRelays,
                        'failed_relays' => $failedRelays
                    ];
                } else {
                    $this->logger->warning("No relays accepted the event. Retrying...");
                    if (!empty($failedRelays)) {
                        $this->logger->warning("  Rejected by: " . implode(", ", $failedRelays));
                    }
                    $retryCount++;
                    sleep(5);
                }
            } catch (TypeError $e) {
                $this->logger->error("Sending to relays did not work. Will be retried.");
                $retryCount++;
                sleep(5);
            } catch (Exception $e) {
                // Handle other exceptions, including invalid status code
                $this->logger->error("Error sending to relays: " . $e->getMessage() . ". Will be retried.");
                $retryCount++;
                sleep(5);
            }
        }
        
        // If we've exhausted all retries and custom relays were provided, don't try the default relay
        if (!empty($customRelays)) {
            $this->logger->warning("All specified relays rejected the event.");
            
            // Provide specific feedback for deletion events
            if ($eventKind === 5) {
                $this->logger->warning("Deletion event was rejected by all specified relays. This could be because:");
                $this->logger->warning("1. The relays don't accept deletion events (kind 5)");
                $this->logger->warning("2. The private key used doesn't match the original event creator");
                $this->logger->warning("3. The event ID might not exist on these relays");
            }
            
            return [
                'success' => false,
                'message' => 'Event was rejected by all specified relays',
                'event_id' => $eventId,
                'event_kind' => $eventKind,
                'failed_relays' => $failedRelays
            ];
        }
        
        // If no custom relays were provided, try with just the default relay
        try {
            // Use the appropriate default relay based on event kind
            $defaultRelay = ($eventKind === 1) ? 
                $this->relayService->getRelayList(1, [$this->appConfig['kind1_default']]) : 
                $this->relayService->getRelayList(0, [$this->appConfig['default']]);
            
            $this->logger->info("Trying with default relay: " . $defaultRelay[0]->getUrl());
            
            $singleRelaySet = new RelaySet();
            $singleRelaySet->setRelays($defaultRelay);
            $singleRelaySet->setMessage($eventMessage);
            
            // Use our helper function to handle errors
            $result = $this->executeWithErrorHandling(function() use ($singleRelaySet) {
                return $singleRelaySet->send();
            }, 'RelaySet.php');
            
            // Check if the default relay accepted the event
            $defaultSuccess = false;
            foreach ($result as $relayUrl => $response) {
                if (is_object($response) && method_exists($response, 'isSuccess')) {
                    $isSuccess = $response->isSuccess();
                    if ($isSuccess) {
                        $defaultSuccess = true;
                        $successfulRelays[] = $relayUrl;
                    } else {
                        $failedRelays[] = $relayUrl;
                    }
                }
            }
            
            if ($defaultSuccess) {
                $this->logger->info("Event successfully sent to default relay:");
                $this->logger->info("  Accepted by: " . implode(", ", $successfulRelays));
                if (!empty($failedRelays)) {
                    $this->logger->info("  Rejected by: " . implode(", ", $failedRelays));
                }
                return [
                    'success' => true,
                    'message' => 'Event sent successfully to default relay',
                    'event_id' => $eventId,
                    'successful_relays' => $successfulRelays,
                    'failed_relays' => $failedRelays
                ];
            } else {
                $this->logger->error("Default relay did not accept the event.");
                $this->logger->error("  Rejected by: " . $defaultRelay[0]->getUrl());
                
                // Provide specific feedback for deletion events
                if ($eventKind === 5) {
                    $this->logger->error("Deletion event was rejected by all relays. This could be because:");
                    $this->logger->error("1. The relays don't accept deletion events (kind 5)");
                    $this->logger->error("2. The private key used doesn't match the original event creator");
                    $this->logger->error("3. The event ID might not exist on these relays");
                }
                
                return [
                    'success' => false,
                    'message' => 'Event was rejected by all relays including default relay',
                    'event_id' => $eventId,
                    'event_kind' => $eventKind,
                    'failed_relays' => array_merge($failedRelays, [$defaultRelay[0]->getUrl()])
                ];
            }
        } catch (Exception $e) {
            // If even the default relay fails, return a detailed error response
            $this->logger->error("All relays including default relay failed with error: " . $e->getMessage());
            
            // Provide specific feedback for deletion events
            if ($eventKind === 5) {
                $this->logger->error("Deletion event could not be sent to any relay. This could be because:");
                $this->logger->error("1. The relays don't accept deletion events (kind 5)");
                $this->logger->error("2. The private key used doesn't match the original event creator");
                $this->logger->error("3. The event ID might not exist on these relays");
                $this->logger->error("4. Network or connection issues with the relays");
            }
            
            return [
                'success' => false,
                'message' => 'Failed to send event to any relay: ' . $e->getMessage(),
                'event_id' => $eventId,
                'event_kind' => $eventKind,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute a callback function with error handling
     *
     * @param callable $callback The function to execute
     * @param string $filePattern File pattern to match for error suppression
     * @return mixed The result of the callback function
     */
    private function executeWithErrorHandling(callable $callback, string $filePattern = 'RelaySet.php'): mixed
    {
        // Set up a custom error handler to catch warnings
        set_error_handler(function($errno, $errstr, $errfile, $errline) use ($filePattern) {
            // Only handle warnings from the specified file pattern
            if (($errno === E_WARNING || $errno === E_NOTICE) && 
                strpos($errfile, $filePattern) !== false) {
                // Suppress the warning
                return true; // Prevent the standard error handler from running
            }
            // For other errors, use the standard error handler
            return false;
        });
        
        try {
            // Execute the callback function
            $result = $callback();
            
            // Restore the previous error handler
            restore_error_handler();
            
            return $result;
        } catch (Exception $e) {
            // Restore the error handler even if an exception occurs
            restore_error_handler();
            throw $e; // Re-throw the exception
        }
    }
    
}

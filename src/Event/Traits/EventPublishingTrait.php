<?php

namespace Sybil\Event\Traits;

use swentel\nostr\Event\Event;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Sign\Sign;
use Sybil\Exception\EventPublishException;
use Sybil\Exception\RelayConnectionException;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;
use Sybil\Utility\Key\KeyUtility;
use Sybil\Utility\Validation\EventValidator;
use Sybil\Utility\Security\SignatureVerifier;
use Sybil\Utility\Log\LoggerFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

trait EventPublishingTrait
{
    /**
     * @var LoggerInterface Logger instance
     */
    protected LoggerInterface $logger;

    /**
     * @var EventValidator Event validator
     */
    protected EventValidator $validator;

    /**
     * @var SignatureVerifier Signature verifier
     */
    protected SignatureVerifier $signatureVerifier;

    /**
     * Initialize the trait
     */
    protected function initializeEventPublishing(?LoggerInterface $logger = null): void
    {
        $this->logger = $logger ?? new NullLogger();
        $this->validator = new EventValidator($this->logger);
        $this->signatureVerifier = new SignatureVerifier($this->logger);
    }

    /**
     * Publish the event to a specific relay
     * 
     * @param string $relayUrl The relay URL
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @return array The result of sending the event
     * @throws RelayConnectionException If relay connection fails
     * @throws EventPublishException If event publishing fails
     * @throws ValidationException If event validation fails
     * @throws AuthenticationException If authentication fails
     */
    protected function publishToRelay(string $relayUrl, ?string $keyEnvVar = null): array
    {
        $this->logger->debug('Publishing event to relay', ['relay_url' => $relayUrl]);

        try {
            if (empty($relayUrl)) {
                throw new RelayConnectionException('', 'No relay URL provided');
            }

            // Validate the event
            $this->logger->debug('Validating event');
            $this->validate();

            // Prepare the event
            $this->logger->debug('Preparing event');
            $this->prepare();

            // Create and sign the event
            $this->logger->debug('Building event');
            $event = $this->buildEvent();
            
            $this->logger->debug('Signing event');
            $this->signEvent($event, $keyEnvVar);

            // Verify the signature
            $this->logger->debug('Verifying event signature');
            if (!$this->signatureVerifier->verify($event)) {
                throw new AuthenticationException(
                    'Event signature verification failed',
                    AuthenticationException::ERROR_SIGNATURE_VERIFICATION
                );
            }

            // Get the event ID
            $eventId = $event->getId();
            if (empty($eventId)) {
                throw new EventPublishException('', $relayUrl, 'Failed to generate event ID');
            }

            // Send the event
            $this->logger->debug('Sending event', ['event_id' => $eventId]);
            $result = $this->sendEvent($event, $relayUrl);
            if (!$result['success']) {
                throw new EventPublishException(
                    $eventId,
                    $relayUrl,
                    $result['message'] ?? 'Failed to publish event'
                );
            }

            $this->logger->info('Event published successfully', [
                'event_id' => $eventId,
                'relay_url' => $relayUrl
            ]);

            return [
                'success' => true,
                'event_id' => $eventId,
                'relay_url' => $relayUrl
            ];

        } catch (RelayConnectionException $e) {
            $this->logger->error('Relay connection failed', [
                'relay_url' => $relayUrl,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (EventPublishException $e) {
            $this->logger->error('Event publish failed', [
                'event_id' => $e->getEventId(),
                'relay_url' => $e->getRelayUrl(),
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (ValidationException $e) {
            $this->logger->error('Event validation failed', [
                'relay_url' => $relayUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (AuthenticationException $e) {
            $this->logger->error('Authentication failed', [
                'relay_url' => $relayUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error', [
                'relay_url' => $relayUrl,
                'event_id' => $eventId ?? '',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new EventPublishException(
                $eventId ?? '',
                $relayUrl,
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Sign the event with the private key
     * 
     * @param Event $event The event to sign
     * @param string|null $keyEnvVar Optional custom environment variable name for the secret key
     * @throws AuthenticationException If signing fails
     */
    protected function signEvent(Event $event, ?string $keyEnvVar = null): void
    {
        $this->logger->debug('Signing event');

        try {
            $privateKey = KeyUtility::getPrivateKey($keyEnvVar);
            if (empty($privateKey)) {
                throw new AuthenticationException(
                    'Private key not found',
                    AuthenticationException::ERROR_KEY_NOT_FOUND
                );
            }
            
            $signer = new Sign();
            $signer->signEvent($event, $privateKey);
            
            $this->logger->info('Event signed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to sign event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new AuthenticationException(
                'Failed to sign event',
                AuthenticationException::ERROR_SIGNING,
                $e
            );
        }
    }

    /**
     * Send the event to a relay
     * 
     * @param Event $event The event to send
     * @param string $relayUrl The relay URL
     * @return array The result of sending the event
     * @throws RelayConnectionException If relay connection fails
     */
    protected function sendEvent(Event $event, string $relayUrl): array
    {
        $this->logger->debug('Sending event to relay', ['relay_url' => $relayUrl]);

        try {
            $eventMessage = new EventMessage($event);
            $relay = new Relay($relayUrl);
            
            $result = $this->sendEventWithRetry($eventMessage, [$relay]);
            
            if ($result['success']) {
                $this->logger->info('Event sent successfully', ['relay_url' => $relayUrl]);
            } else {
                $this->logger->warning('Event send failed', [
                    'relay_url' => $relayUrl,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send event', [
                'relay_url' => $relayUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RelayConnectionException(
                $relayUrl,
                'Failed to send event: ' . $e->getMessage()
            );
        }
    }
} 
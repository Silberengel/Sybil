<?php

namespace Sybil\Service;

use Psr\Log\LoggerInterface;
use Sybil\Exception\RelayConnectionException;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Message\FilterMessage;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use WebSocket\Client;
use WebSocket\Exception\WebSocketException;
use Sybil\Utility\Relay\RelayAuth;
use Sybil\Utility\Key\KeyUtility;

class RelayQueryService
{
    private Client $client;
    private LoggerInterface $logger;
    private array $activeSubscriptions = [];
    private array $relayAuthStates = [];
    private array $relayLimits = [];
    private RelayAuth $relayAuth;

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->relayAuth = new RelayAuth($logger);
    }

    /**
     * Query a single relay with a filter
     *
     * @param string $relayUrl The relay URL to query
     * @param Filter $filter The filter to apply
     * @param int $timeout Timeout in seconds
     * @param bool $persistent Whether to keep the subscription active
     * @return array The events found
     * @throws RelayConnectionException If the relay connection fails
     */
    public function queryRelay(string $relayUrl, Filter $filter, int $timeout = 10, bool $persistent = false): array
    {
        try {
            $relay = new Relay($relayUrl);
            $filterMessage = new FilterMessage($filter);
            
            $events = [];
            $subscriptionId = uniqid('query_', true);
            
            // Check relay limits
            $this->checkRelayLimits($relayUrl);
            
            // Authenticate if required
            if ($this->isAuthenticationRequired($relayUrl)) {
                $this->authenticate($relayUrl);
            }
            
            $relay->on('event', function ($event) use (&$events, $subscriptionId) {
                $events[] = $event;
                $this->logger->debug('Received event', [
                    'subscription_id' => $subscriptionId,
                    'event_id' => $event['id'] ?? 'unknown'
                ]);
            });
            
            $relay->on('eose', function () use ($relay, $subscriptionId, $persistent) {
                if (!$persistent) {
                    $this->closeSubscription($relay, $subscriptionId);
                }
            });
            
            $relay->connect();
            $relay->send($filterMessage);
            
            // Store subscription if persistent
            if ($persistent) {
                $this->activeSubscriptions[$subscriptionId] = [
                    'relay' => $relay,
                    'filter' => $filter,
                    'started_at' => time(),
                    'last_event' => null
                ];
            }
            
            // Wait for events with timeout
            $startTime = time();
            while (time() - $startTime < $timeout) {
                $relay->read();
                if (empty($events)) {
                    usleep(100000); // Sleep for 100ms
                }
            }
            
            if (!$persistent) {
                $relay->disconnect();
            }
            
            return $events;
            
        } catch (\Exception $e) {
            throw new RelayConnectionException(
                sprintf('Failed to query relay %s: %s', $relayUrl, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Close a subscription
     *
     * @param Relay $relay The relay instance
     * @param string $subscriptionId The subscription ID
     */
    private function closeSubscription(Relay $relay, string $subscriptionId): void
    {
        try {
            $relay->close($subscriptionId);
            unset($this->activeSubscriptions[$subscriptionId]);
            $this->logger->info('Closed subscription', ['subscription_id' => $subscriptionId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to close subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get active subscriptions
     *
     * @return array The active subscriptions
     */
    public function getActiveSubscriptions(): array
    {
        return $this->activeSubscriptions;
    }

    /**
     * Close all active subscriptions
     */
    public function closeAllSubscriptions(): void
    {
        foreach ($this->activeSubscriptions as $subscriptionId => $subscription) {
            $this->closeSubscription($subscription['relay'], $subscriptionId);
        }
    }

    /**
     * Check if authentication is required for a relay
     *
     * @param string $relayUrl The relay URL
     * @return bool Whether authentication is required
     */
    private function isAuthenticationRequired(string $relayUrl): bool
    {
        if (!isset($this->relayLimits[$relayUrl])) {
            $this->relayLimits[$relayUrl] = $this->getRelayInfo($relayUrl);
        }
        return $this->relayLimits[$relayUrl]['auth_required'] ?? false;
    }

    /**
     * Authenticate with a relay
     *
     * @param string $relayUrl The relay URL
     * @throws RelayConnectionException If authentication fails
     */
    private function authenticate(string $relayUrl): void
    {
        if (isset($this->relayAuthStates[$relayUrl]) && $this->relayAuthStates[$relayUrl]) {
            return; // Already authenticated
        }

        try {
            $this->client->connect($relayUrl);
            $this->client->send(json_encode(['AUTH', 'challenge']));
            
            $timeout = time() + 10;
            while (time() < $timeout) {
                $response = $this->client->receive();
                $data = json_decode($response, true);
                
                if ($data[0] === 'AUTH' && isset($data[1])) {
                    $challenge = $data[1];
                    $authData = $this->relayAuth->authenticate($challenge);
                    
                    // Send authentication response
                    $this->client->send(json_encode(['AUTH', $authData]));
                    
                    // Wait for authentication result
                    $timeout = time() + 5;
                    while (time() < $timeout) {
                        $response = $this->client->receive();
                        $data = json_decode($response, true);
                        
                        if ($data[0] === 'OK' && isset($data[1]) && $data[1] === 'auth') {
                            $this->relayAuthStates[$relayUrl] = true;
                            $this->logger->info('Successfully authenticated with relay', [
                                'relay' => $relayUrl,
                                'pubkey' => $this->relayAuth->getPublicKey()
                            ]);
                            return;
                        }
                    }
                    
                    throw new RelayConnectionException(
                        'Authentication response timeout',
                        RelayConnectionException::ERROR_AUTHENTICATION
                    );
                }
            }
            
            throw new RelayConnectionException(
                'Authentication challenge timeout',
                RelayConnectionException::ERROR_AUTHENTICATION
            );
        } catch (\Exception $e) {
            throw new RelayConnectionException(
                'Authentication failed: ' . $e->getMessage(),
                RelayConnectionException::ERROR_AUTHENTICATION,
                $e
            );
        }
    }

    /**
     * Check relay limits before querying
     *
     * @param string $relayUrl The relay URL
     * @throws RelayConnectionException If limits are exceeded
     */
    private function checkRelayLimits(string $relayUrl): void
    {
        if (!isset($this->relayLimits[$relayUrl])) {
            $this->relayLimits[$relayUrl] = $this->getRelayInfo($relayUrl);
        }

        $limits = $this->relayLimits[$relayUrl];
        $activeCount = count($this->activeSubscriptions);

        if (isset($limits['max_subscriptions']) && $activeCount >= $limits['max_subscriptions']) {
            throw new RelayConnectionException(
                'Maximum subscription limit reached',
                RelayConnectionException::ERROR_RATE_LIMIT
            );
        }
    }

    /**
     * Query multiple relays with a filter
     *
     * @param array $relayUrls The relay URLs to query
     * @param Filter $filter The filter to apply
     * @param int $timeout Timeout in seconds
     * @return array The events found
     */
    public function queryRelays(array $relayUrls, Filter $filter, int $timeout = 10): array
    {
        $events = [];
        foreach ($relayUrls as $relayUrl) {
            try {
                $relayEvents = $this->queryRelay($relayUrl, $filter, $timeout);
                $events = array_merge($events, $relayEvents);
            } catch (RelayConnectionException $e) {
                $this->logger->warning(sprintf('Failed to query relay %s: %s', $relayUrl, $e->getMessage()));
            }
        }
        return $events;
    }

    /**
     * Create a filter for querying events
     *
     * @param array $parameters The filter parameters
     * @return Filter The created filter
     */
    public function createFilter(array $parameters): Filter
    {
        $filter = new Filter();
        
        if (isset($parameters['kinds'])) {
            $filter->setKinds($parameters['kinds']);
        }
        
        if (isset($parameters['authors'])) {
            $filter->setAuthors($parameters['authors']);
        }
        
        if (isset($parameters['tags'])) {
            $filter->setTags($parameters['tags']);
        }
        
        if (isset($parameters['since'])) {
            $filter->setSince($parameters['since']);
        }
        
        if (isset($parameters['until'])) {
            $filter->setUntil($parameters['until']);
        }
        
        if (isset($parameters['limit'])) {
            $filter->setLimit($parameters['limit']);
        }

        return $filter;
    }

    /**
     * Query events based on the filter
     *
     * @param array $filter The filter parameters
     * @return array The events found
     */
    public function query(array $filter): array
    {
        $filterObj = $this->createFilter($filter);
        $message = new FilterMessage($filterObj);
        
        try {
            $this->client->connect();
            $this->client->send($message->toJson());
            
            $events = [];
            while (true) {
                $response = $this->client->receive();
                $data = json_decode($response, true);
                
                if ($data[0] === 'EOSE') {
                    break;
                }
                
                if ($data[0] === 'EVENT') {
                    $event = new EventMessage($data[1]);
                    $events[] = $event->getEvent();
                }
            }
            
            return $events;
        } catch (WebSocketException $e) {
            $this->logger->error('Failed to query relay: ' . $e->getMessage());
            throw new \RuntimeException('Failed to query relay: ' . $e->getMessage());
        } finally {
            $this->client->close();
        }
    }

    /**
     * Publish an event to the relay
     *
     * @param Event $event The event to publish
     */
    public function publish(Event $event): void
    {
        $message = new EventMessage($event);
        
        try {
            $this->client->connect();
            $this->client->send($message->toJson());
        } catch (WebSocketException $e) {
            $this->logger->error('Failed to publish event: ' . $e->getMessage());
            throw new \RuntimeException('Failed to publish event: ' . $e->getMessage());
        } finally {
            $this->client->close();
        }
    }

    /**
     * Get information about a relay
     *
     * @param string $relayUrl The relay URL to query
     * @return array The relay information
     * @throws RelayConnectionException If the relay connection fails
     */
    public function getRelayInfo(string $relayUrl): array
    {
        try {
            $this->client->connect($relayUrl);
            $this->client->send(json_encode(['REQ', 'info', ['kinds' => [0]]]));

            $info = [];
            $timeout = time() + 10; // 10 second timeout

            while (time() < $timeout) {
                $message = $this->client->receive();
                $data = json_decode($message->getContent(), true);

                if (!is_array($data)) {
                    continue;
                }

                if ($data[0] === 'EVENT' && $data[1] === 'info') {
                    $info = $data[2];
                    break;
                }
            }

            if (empty($info)) {
                throw new \RuntimeException("No response from relay {$relayUrl}");
            }

            return $info;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to connect to relay {$relayUrl}: {$e->getMessage()}");
        }
    }
} 
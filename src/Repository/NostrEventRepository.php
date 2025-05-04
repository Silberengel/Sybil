<?php

namespace Sybil\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sybil\Entity\NostrEvent;
use Sybil\Service\RelayQueryService;
use Sybil\Exception\NostrException;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;
use Sybil\Security\AuthenticationManager;
use Sybil\Utility\Log\LoggerFactory;
use Sybil\Utility\Validation\EventValidator;
use swentel\nostr\Filter\Filter;

/**
 * @extends EntityRepository<NostrEvent>
 */
class NostrEventRepository extends EntityRepository
{
    private RelayQueryService $relayQueryService;
    private LoggerInterface $logger;
    private AuthenticationManager $authManager;
    private EventValidator $validator;

    public function __construct(
        EntityManagerInterface $entityManager, 
        RelayQueryService $relayQueryService,
        ?LoggerInterface $logger = null,
        ?AuthenticationManager $authManager = null
    ) {
        parent::__construct($entityManager, $entityManager->getClassMetadata(NostrEvent::class));
        $this->relayQueryService = $relayQueryService;
        $this->logger = $logger ?? LoggerFactory::createLogger('nostr_event_repository');
        $this->authManager = $authManager ?? new AuthenticationManager($this->logger);
        $this->validator = new EventValidator($this->logger);
    }

    /**
     * Find events by time range from the database
     * 
     * @throws ValidationException If date range is invalid
     */
    public function findByTimeRange(\DateTimeInterface $since, \DateTimeInterface $until): array
    {
        $this->logger->debug('Finding events by time range', [
            'since' => $since->format('Y-m-d H:i:s'),
            'until' => $until->format('Y-m-d H:i:s')
        ]);

        if ($since > $until) {
            throw new ValidationException('Start date must be before end date');
        }

        try {
            $result = $this->createQueryBuilder('e')
                ->where('e.createdAt BETWEEN :since AND :until')
                ->setParameter('since', $since)
                ->setParameter('until', $until)
                ->orderBy('e.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $this->logger->info('Found events by time range', [
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events by time range', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by kind from the database
     * 
     * @throws ValidationException If kind is invalid
     */
    public function findByKind(int $kind): array
    {
        $this->logger->debug('Finding events by kind', ['kind' => $kind]);

        if ($kind < 0) {
            throw new ValidationException('Event kind must be non-negative');
        }

        try {
            $result = $this->createQueryBuilder('e')
                ->where('e.kind = :kind')
                ->setParameter('kind', $kind)
                ->orderBy('e.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $this->logger->info('Found events by kind', [
                'kind' => $kind,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events by kind', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by author from the database
     * 
     * @throws ValidationException If pubkey is invalid
     */
    public function findByAuthor(string $pubkey): array
    {
        $this->logger->debug('Finding events by author', ['pubkey' => $pubkey]);

        if (empty($pubkey)) {
            throw new ValidationException('Public key cannot be empty');
        }

        try {
            $result = $this->createQueryBuilder('e')
                ->where('e.pubkey = :pubkey')
                ->setParameter('pubkey', $pubkey)
                ->orderBy('e.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $this->logger->info('Found events by author', [
                'pubkey' => $pubkey,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events by author', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by filter from the database
     * 
     * @throws ValidationException If filter is invalid
     */
    public function findByFilter(array $filter): array
    {
        $this->logger->debug('Finding events by filter', ['filter' => $filter]);

        try {
            $qb = $this->createQueryBuilder('e');

            if (isset($filter['kinds'])) {
                $qb->andWhere('e.kind IN (:kinds)')
                    ->setParameter('kinds', $filter['kinds']);
            }

            if (isset($filter['authors'])) {
                $qb->andWhere('e.pubkey IN (:authors)')
                    ->setParameter('authors', $filter['authors']);
            }

            if (isset($filter['since'])) {
                $qb->andWhere('e.createdAt >= :since')
                    ->setParameter('since', $filter['since']);
            }

            if (isset($filter['until'])) {
                $qb->andWhere('e.createdAt <= :until')
                    ->setParameter('until', $filter['until']);
            }

            if (isset($filter['limit'])) {
                $qb->setMaxResults($filter['limit']);
            }

            $result = $qb->orderBy('e.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $this->logger->info('Found events by filter', [
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events by filter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by kind from relays
     * 
     * @throws ValidationException If kind or relay URLs are invalid
     */
    public function findFromRelaysByKind(int $kind, array $relayUrls): array
    {
        $this->logger->debug('Finding events from relays by kind', [
            'kind' => $kind,
            'relay_count' => count($relayUrls)
        ]);

        if ($kind < 0) {
            throw new ValidationException('Event kind must be non-negative');
        }

        if (empty($relayUrls)) {
            throw new ValidationException('Relay URLs cannot be empty');
        }

        try {
            $filter = $this->relayQueryService->createFilter(kinds: [$kind]);
            $result = $this->relayQueryService->queryRelays($relayUrls, $filter);

            $this->logger->info('Found events from relays by kind', [
                'kind' => $kind,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events from relays by kind', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by pubkey from relays
     * 
     * @throws ValidationException If pubkey or relay URLs are invalid
     */
    public function findFromRelaysByPubkey(string $pubkey, array $relayUrls): array
    {
        $this->logger->debug('Finding events from relays by pubkey', [
            'pubkey' => $pubkey,
            'relay_count' => count($relayUrls)
        ]);

        if (empty($pubkey)) {
            throw new ValidationException('Public key cannot be empty');
        }

        if (empty($relayUrls)) {
            throw new ValidationException('Relay URLs cannot be empty');
        }

        try {
            $filter = $this->relayQueryService->createFilter(authors: [$pubkey]);
            $result = $this->relayQueryService->queryRelays($relayUrls, $filter);

            $this->logger->info('Found events from relays by pubkey', [
                'pubkey' => $pubkey,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events from relays by pubkey', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find unpublished events from the database
     */
    public function findUnpublished(): array
    {
        $this->logger->debug('Finding unpublished events');

        try {
            $result = $this->createQueryBuilder('e')
                ->andWhere('e.published = :published')
                ->setParameter('published', false)
                ->orderBy('e.createdAt', 'ASC')
                ->getQuery()
                ->getResult();

            $this->logger->info('Found unpublished events', [
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find unpublished events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by tag from the database
     * 
     * @throws ValidationException If tag parameters are invalid
     */
    public function findByTag(string $tagName, string $tagValue): array
    {
        $this->logger->debug('Finding events by tag', [
            'tag_name' => $tagName,
            'tag_value' => $tagValue
        ]);

        if (empty($tagName) || empty($tagValue)) {
            throw new ValidationException('Tag name and value cannot be empty');
        }

        try {
            $result = $this->createQueryBuilder('e')
                ->andWhere('JSON_CONTAINS(e.tags, :tag) = 1')
                ->setParameter('tag', json_encode([$tagName, $tagValue]))
                ->orderBy('e.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            $this->logger->info('Found events by tag', [
                'tag_name' => $tagName,
                'tag_value' => $tagValue,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events by tag', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by tag from relays
     * 
     * @throws ValidationException If tag parameters or relay URLs are invalid
     */
    public function findFromRelaysByTag(string $tagName, string $tagValue, array $relayUrls): array
    {
        $this->logger->debug('Finding events from relays by tag', [
            'tag_name' => $tagName,
            'tag_value' => $tagValue,
            'relay_count' => count($relayUrls)
        ]);

        if (empty($tagName) || empty($tagValue)) {
            throw new ValidationException('Tag name and value cannot be empty');
        }

        if (empty($relayUrls)) {
            throw new ValidationException('Relay URLs cannot be empty');
        }

        try {
            $filter = $this->relayQueryService->createFilter(tags: [[$tagName, $tagValue]]);
            $result = $this->relayQueryService->queryRelays($relayUrls, $filter);

            $this->logger->info('Found events from relays by tag', [
                'tag_name' => $tagName,
                'tag_value' => $tagValue,
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events from relays by tag', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Find events by custom filter from relays
     * 
     * @throws ValidationException If filter or relay URLs are invalid
     */
    public function findFromRelaysByFilter(Filter $filter, array $relayUrls): array
    {
        $this->logger->debug('Finding events from relays by filter', [
            'relay_count' => count($relayUrls)
        ]);

        if (empty($relayUrls)) {
            throw new ValidationException('Relay URLs cannot be empty');
        }

        try {
            $result = $this->relayQueryService->queryRelays($relayUrls, $filter);

            $this->logger->info('Found events from relays by filter', [
                'count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find events from relays by filter', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to find events: ' . $e->getMessage());
        }
    }

    /**
     * Save an event to the database
     * 
     * @throws AuthenticationException If user is not authorized
     * @throws ValidationException If event is invalid
     */
    public function save(NostrEvent $event): void
    {
        $this->logger->debug('Saving event', [
            'event_id' => $event->getId(),
            'pubkey' => $event->getPubkey()
        ]);

        // Verify authentication
        if (!$this->authManager->isAuthenticated()) {
            throw new AuthenticationException('User must be authenticated to save events');
        }

        // Validate event
        $this->validator->validate($event);

        try {
            $this->getEntityManager()->persist($event);
            $this->getEntityManager()->flush();

            $this->logger->info('Event saved successfully', [
                'event_id' => $event->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to save event: ' . $e->getMessage());
        }
    }

    /**
     * Remove an event from the database
     * 
     * @throws AuthenticationException If user is not authorized
     * @throws ValidationException If event is invalid
     */
    public function remove(NostrEvent $event): void
    {
        $this->logger->debug('Removing event', [
            'event_id' => $event->getId(),
            'pubkey' => $event->getPubkey()
        ]);

        // Verify authentication
        if (!$this->authManager->isAuthenticated()) {
            throw new AuthenticationException('User must be authenticated to remove events');
        }

        // Verify authorization (only event author can remove)
        if (!$this->authManager->isAuthorized($event->getPubkey())) {
            throw new AuthenticationException('Only event author can remove events');
        }

        try {
            $this->getEntityManager()->remove($event);
            $this->getEntityManager()->flush();

            $this->logger->info('Event removed successfully', [
                'event_id' => $event->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to remove event: ' . $e->getMessage());
        }
    }

    /**
     * Sync events from relays to database
     * 
     * @throws AuthenticationException If user is not authorized
     * @throws ValidationException If parameters are invalid
     */
    public function syncFromRelays(array $relays, array $filter): void
    {
        $this->logger->debug('Syncing events from relays', [
            'relay_count' => count($relays),
            'filter' => $filter
        ]);

        // Verify authentication
        if (!$this->authManager->isAuthenticated()) {
            throw new AuthenticationException('User must be authenticated to sync events');
        }

        if (empty($relays)) {
            throw new ValidationException('Relay list cannot be empty');
        }

        try {
            $events = $this->relayQueryService->queryRelays($relays, $filter);
            foreach ($events as $eventData) {
                $event = new NostrEvent();
                $event->fromArray($eventData);
                $this->validator->validate($event);
                $this->getEntityManager()->persist($event);
            }
            $this->getEntityManager()->flush();

            $this->logger->info('Events synced successfully', [
                'count' => count($events)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync events from relays', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new NostrException('Failed to sync events: ' . $e->getMessage());
        }
    }
} 
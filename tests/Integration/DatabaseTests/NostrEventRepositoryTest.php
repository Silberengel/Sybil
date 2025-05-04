<?php

namespace Sybil\Tests\Integration\DatabaseTests;

use Sybil\Tests\Integration\SybilIntegrationTestCase;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Sybil\Entity\NostrEventEntity;
use Sybil\Repository\NostrEventRepository;
use Sybil\Service\RelayQueryService;
use Sybil\Exception\ValidationException;
use Sybil\Exception\AuthenticationException;

class NostrEventRepositoryTest extends SybilIntegrationTestCase
{
    private EntityManager $entityManager;
    private NostrEventRepository $repository;
    private string $testDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test database
        $this->testDbPath = $this->testDataDir . '/test_' . uniqid() . '.db';
        
        // Create EntityManager with test configuration
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 3) . '/src/Entity'],
            true
        );

        $connection = \Doctrine\DBAL\DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $this->testDbPath,
        ], $config);

        $this->entityManager = new EntityManager($connection, $config);

        // Create schema
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        // Create repository with mock services
        $relayQueryService = $this->createMock(RelayQueryService::class);
        $this->repository = new NostrEventRepository(
            $this->entityManager,
            $relayQueryService,
            $this->logger
        );

        $this->logger->info('Database test environment initialized', [
            'db_path' => $this->testDbPath
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test database
        $this->entityManager->close();
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        parent::tearDown();
    }

    public function testSaveAndFindEvent(): void
    {
        // Create test event
        $event = new NostrEventEntity();
        $event->setId(str_pad(bin2hex(random_bytes(32)), 64, '0'))
            ->setPubkey(str_pad(bin2hex(random_bytes(32)), 64, '0'))
            ->setCreatedAt(time())
            ->setKind(1)
            ->setContent('Test event content')
            ->setTags([])
            ->setSig(str_pad(bin2hex(random_bytes(64)), 128, '0'));

        // Save event
        $this->repository->save($event);

        // Find event by ID
        $foundEvent = $this->entityManager->find(NostrEventEntity::class, $event->getId());
        $this->assertNotNull($foundEvent);
        $this->assertEquals($event->getId(), $foundEvent->getId());
        $this->assertEquals($event->getContent(), $foundEvent->getContent());
    }

    public function testFindByTimeRange(): void
    {
        // Create test events with different timestamps
        $now = time();
        $event1 = $this->createTestEvent($now - 3600); // 1 hour ago
        $event2 = $this->createTestEvent($now); // now
        $event3 = $this->createTestEvent($now + 3600); // 1 hour later

        $this->repository->save($event1);
        $this->repository->save($event2);
        $this->repository->save($event3);

        // Test finding events in time range
        $since = new \DateTime('@' . ($now - 7200)); // 2 hours ago
        $until = new \DateTime('@' . ($now + 7200)); // 2 hours later

        $events = $this->repository->findByTimeRange($since, $until);
        $this->assertCount(3, $events);
    }

    public function testFindByKind(): void
    {
        // Create test events with different kinds
        $event1 = $this->createTestEvent(time(), 1);
        $event2 = $this->createTestEvent(time(), 2);
        $event3 = $this->createTestEvent(time(), 1);

        $this->repository->save($event1);
        $this->repository->save($event2);
        $this->repository->save($event3);

        // Test finding events by kind
        $events = $this->repository->findByKind(1);
        $this->assertCount(2, $events);
    }

    public function testFindByAuthor(): void
    {
        // Create test events with different authors
        $pubkey1 = str_pad(bin2hex(random_bytes(32)), 64, '0');
        $pubkey2 = str_pad(bin2hex(random_bytes(32)), 64, '0');

        $event1 = $this->createTestEvent(time(), 1, $pubkey1);
        $event2 = $this->createTestEvent(time(), 1, $pubkey2);
        $event3 = $this->createTestEvent(time(), 1, $pubkey1);

        $this->repository->save($event1);
        $this->repository->save($event2);
        $this->repository->save($event3);

        // Test finding events by author
        $events = $this->repository->findByAuthor($pubkey1);
        $this->assertCount(2, $events);
    }

    public function testFindByTag(): void
    {
        // Create test events with different tags
        $event1 = $this->createTestEvent(time(), 1, null, [['t', 'test1']]);
        $event2 = $this->createTestEvent(time(), 1, null, [['t', 'test2']]);
        $event3 = $this->createTestEvent(time(), 1, null, [['t', 'test1']]);

        $this->repository->save($event1);
        $this->repository->save($event2);
        $this->repository->save($event3);

        // Test finding events by tag
        $events = $this->repository->findByTag('t', 'test1');
        $this->assertCount(2, $events);
    }

    public function testRemoveEvent(): void
    {
        // Create and save test event
        $event = $this->createTestEvent(time());
        $this->repository->save($event);

        // Remove event
        $this->repository->remove($event);

        // Verify event is removed
        $foundEvent = $this->entityManager->find(NostrEventEntity::class, $event->getId());
        $this->assertNull($foundEvent);
    }

    public function testValidationException(): void
    {
        // Create invalid event (missing required fields)
        $event = new NostrEventEntity();

        // Test that saving invalid event throws ValidationException
        $this->expectException(ValidationException::class);
        $this->repository->save($event);
    }

    private function createTestEvent(
        int $timestamp,
        int $kind = 1,
        ?string $pubkey = null,
        array $tags = []
    ): NostrEventEntity {
        $event = new NostrEventEntity();
        $event->setId(str_pad(bin2hex(random_bytes(32)), 64, '0'))
            ->setPubkey($pubkey ?? str_pad(bin2hex(random_bytes(32)), 64, '0'))
            ->setCreatedAt($timestamp)
            ->setKind($kind)
            ->setContent('Test event content')
            ->setTags($tags)
            ->setSig(str_pad(bin2hex(random_bytes(64)), 128, '0'));

        return $event;
    }
} 
<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\SalesOrderDeletedEvent;
use App\EventHandler\SalesOrderDeletedEventHandler;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class SalesOrderDeletedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SalesOrderDeletedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new SalesOrderDeletedEventHandler($this->entityManager);
    }

    /**
     * Creates a mock QueryBuilder that returns the given result from getOneOrNullResult
     */
    private function createQueryBuilderMock(?ItemEvent $result): QueryBuilder
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getOneOrNullResult')->willReturn($result);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }

    public function testSalesOrderDeletedReversesInventoryChanges(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 30;
        $item->quantityAvailable = 70;
        $item->quantityBackOrdered = 10;

        // The most recent ItemEvent that recorded the commitment
        $mostRecentItemEvent = new ItemEvent();
        $mostRecentItemEvent->item = $item;
        $mostRecentItemEvent->eventType = 'sales_order_created';
        $mostRecentItemEvent->quantityChange = -40;
        $mostRecentItemEvent->metadata = json_encode([
            'order_number' => 'SO-TEST-001',
            'quantity_committed' => 30,
            'quantity_backordered' => 10,
        ]);

        $orderState = [
            'id' => 1,
            'orderNumber' => 'SO-TEST-001',
            'lines' => [
                [
                    'item' => ['id' => 1],
                    'quantityOrdered' => 40,
                ],
            ],
        ];

        $event = new SalesOrderDeletedEvent(1, $orderState);

        // Setup repository mocks
        $itemRepository = $this->createMock(EntityRepository::class);
        $itemRepository
            ->method('find')
            ->with(1)
            ->willReturn($item);

        $itemEventRepository = $this->createMock(EntityRepository::class);
        $itemEventRepository
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock($mostRecentItemEvent));

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($itemRepository, $itemEventRepository) {
                if ($class === Item::class) {
                    return $itemRepository;
                }
                if ($class === ItemEvent::class) {
                    return $itemEventRepository;
                }
                return $this->createMock(EntityRepository::class);
            });

        $persistedEntities = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        // Should have persisted: OrderEvent, ItemEvent (reversal), Item
        $this->assertGreaterThanOrEqual(3, count($persistedEntities));
        
        // Check that OrderEvent was created
        $orderEvent = $persistedEntities[0];
        $this->assertInstanceOf(OrderEvent::class, $orderEvent);
        $this->assertEquals('deleted', $orderEvent->eventType);
        
        // Check Item quantities were reversed
        $this->assertEquals(0, $item->quantityCommitted); // 30 - 30 = 0
        $this->assertEquals(100, $item->quantityAvailable); // 100 - 0 = 100
        $this->assertEquals(0, $item->quantityBackOrdered); // 10 - 10 = 0
    }

    public function testSalesOrderDeletedWithFallbackWhenNoMetadata(): void
    {
        // Arrange - test fallback behavior when most recent event has no metadata
        $item = new Item();
        $item->quantityOnHand = 50;
        $item->quantityCommitted = 25;
        $item->quantityAvailable = 25;
        $item->quantityBackOrdered = 0;

        // Most recent ItemEvent without metadata (old format)
        $mostRecentItemEvent = new ItemEvent();
        $mostRecentItemEvent->item = $item;
        $mostRecentItemEvent->eventType = 'sales_order_created';
        $mostRecentItemEvent->quantityChange = -25;
        $mostRecentItemEvent->metadata = null; // No metadata

        $orderState = [
            'id' => 2,
            'orderNumber' => 'SO-TEST-002',
            'lines' => [
                [
                    'item' => ['id' => 2],
                    'quantityOrdered' => 25,
                ],
            ],
        ];

        $event = new SalesOrderDeletedEvent(2, $orderState);

        // Setup repository mocks
        $itemRepository = $this->createMock(EntityRepository::class);
        $itemRepository
            ->method('find')
            ->willReturn($item);

        $itemEventRepository = $this->createMock(EntityRepository::class);
        $itemEventRepository
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock($mostRecentItemEvent));

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($itemRepository, $itemEventRepository) {
                if ($class === Item::class) {
                    return $itemRepository;
                }
                if ($class === ItemEvent::class) {
                    return $itemEventRepository;
                }
                return $this->createMock(EntityRepository::class);
            });

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        ($this->handler)($event);

        // Assert - should use fallback (quantityOrdered as committed, 0 backordered)
        $this->assertEquals(0, $item->quantityCommitted); // 25 - 25 = 0
        $this->assertEquals(50, $item->quantityAvailable); // 50 - 0 = 50
        $this->assertEquals(0, $item->quantityBackOrdered); // No backorder to reverse
    }

    public function testSalesOrderDeletedAfterUpdateReversesCorrectQuantities(): void
    {
        // This test verifies that when an order that has been updated is deleted,
        // the handler correctly reverses the most recent committed/backordered quantities.
        
        // Arrange - item state after order was updated
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 15;
        $item->quantityAvailable = 85;
        $item->quantityBackOrdered = 5;

        // Most recent ItemEvent from the last update (not the original create)
        $mostRecentItemEvent = new ItemEvent();
        $mostRecentItemEvent->item = $item;
        $mostRecentItemEvent->eventType = 'sales_order_updated'; // From a previous update
        $mostRecentItemEvent->quantityChange = -20;
        $mostRecentItemEvent->metadata = json_encode([
            'order_number' => 'SO-TEST-003',
            'quantity_committed' => 15,
            'quantity_backordered' => 5,
        ]);

        $orderState = [
            'id' => 3,
            'orderNumber' => 'SO-TEST-003',
            'lines' => [
                [
                    'item' => ['id' => 1],
                    'quantityOrdered' => 20,
                ],
            ],
        ];

        $event = new SalesOrderDeletedEvent(3, $orderState);

        // Setup repository mocks
        $itemRepository = $this->createMock(EntityRepository::class);
        $itemRepository
            ->method('find')
            ->willReturn($item);

        $itemEventRepository = $this->createMock(EntityRepository::class);
        $itemEventRepository
            ->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock($mostRecentItemEvent));

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) use ($itemRepository, $itemEventRepository) {
                if ($class === Item::class) {
                    return $itemRepository;
                }
                if ($class === ItemEvent::class) {
                    return $itemEventRepository;
                }
                return $this->createMock(EntityRepository::class);
            });

        $this->entityManager
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        ($this->handler)($event);

        // Assert - should reverse the updated quantities, not the original
        $this->assertEquals(0, $item->quantityCommitted); // 15 - 15 = 0
        $this->assertEquals(100, $item->quantityAvailable); // 100 - 0 = 100
        $this->assertEquals(0, $item->quantityBackOrdered); // 5 - 5 = 0
    }
}

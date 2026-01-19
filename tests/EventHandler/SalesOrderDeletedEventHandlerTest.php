<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Event\SalesOrderDeletedEvent;
use App\EventHandler\SalesOrderDeletedEventHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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

    public function testSalesOrderDeletedReversesInventoryChanges(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 30;
        $item->quantityAvailable = 70;
        $item->quantityBackOrdered = 10;

        // The original ItemEvent that recorded the commitment
        $originalItemEvent = new ItemEvent();
        $originalItemEvent->item = $item;
        $originalItemEvent->eventType = 'sales_order_created';
        $originalItemEvent->quantityChange = -40;
        $originalItemEvent->metadata = json_encode([
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
            ->method('findOneBy')
            ->willReturn($originalItemEvent);

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
        // Arrange - test fallback behavior when original event has no metadata
        $item = new Item();
        $item->quantityOnHand = 50;
        $item->quantityCommitted = 25;
        $item->quantityAvailable = 25;
        $item->quantityBackOrdered = 0;

        // Original ItemEvent without metadata (old format)
        $originalItemEvent = new ItemEvent();
        $originalItemEvent->item = $item;
        $originalItemEvent->eventType = 'sales_order_created';
        $originalItemEvent->quantityChange = -25;
        $originalItemEvent->metadata = null; // No metadata

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
            ->method('findOneBy')
            ->willReturn($originalItemEvent);

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
}

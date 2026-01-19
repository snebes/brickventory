<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\SalesOrderUpdatedEvent;
use App\EventHandler\SalesOrderUpdatedEventHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class SalesOrderUpdatedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SalesOrderUpdatedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new SalesOrderUpdatedEventHandler($this->entityManager);
    }

    public function testSalesOrderUpdatedReversesAndAppliesNewQuantities(): void
    {
        // Arrange
        $item = new Item();
        $item->id = 1;
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 30; // Previously committed
        $item->quantityAvailable = 70;
        $item->quantityBackOrdered = 10; // Previously backordered

        // Original ItemEvent from the create
        $originalItemEvent = new ItemEvent();
        $originalItemEvent->item = $item;
        $originalItemEvent->eventType = 'sales_order_created';
        $originalItemEvent->metadata = json_encode([
            'order_number' => 'SO-TEST-001',
            'quantity_committed' => 30,
            'quantity_backordered' => 10,
        ]);

        // New sales order line (updating the order to different quantity)
        $newLine = new SalesOrderLine();
        $newLine->item = $item;
        $newLine->quantityOrdered = 20; // Reduced from 40 to 20

        $salesOrder = new SalesOrder();
        $salesOrder->id = 1;
        $salesOrder->orderNumber = 'SO-TEST-001';
        $salesOrder->lines = new ArrayCollection([$newLine]);

        $previousState = [
            'id' => 1,
            'orderNumber' => 'SO-TEST-001',
            'lines' => [
                [
                    'item' => ['id' => 1],
                    'itemId' => 1,
                    'quantityOrdered' => 40, // Original was 40
                ],
            ],
        ];

        $event = new SalesOrderUpdatedEvent($salesOrder, $previousState);

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
        // After reversal: committed = 30 - 30 = 0, backorder = 10 - 10 = 0, available = 100
        // After new application: committed = 0 + 20 = 20 (since 20 <= 100), available = 100 - 20 = 80
        $this->assertEquals(20, $item->quantityCommitted);
        $this->assertEquals(80, $item->quantityAvailable);
        $this->assertEquals(0, $item->quantityBackOrdered);
    }

    public function testSalesOrderUpdatedWithIncreasedQuantityCreatesBackorder(): void
    {
        // Arrange - start with item that has limited availability
        $item = new Item();
        $item->id = 1;
        $item->quantityOnHand = 50;
        $item->quantityCommitted = 20; // From the original order
        $item->quantityAvailable = 30;
        $item->quantityBackOrdered = 0;

        // Original ItemEvent
        $originalItemEvent = new ItemEvent();
        $originalItemEvent->item = $item;
        $originalItemEvent->eventType = 'sales_order_created';
        $originalItemEvent->metadata = json_encode([
            'quantity_committed' => 20,
            'quantity_backordered' => 0,
        ]);

        // New sales order line (increasing quantity)
        $newLine = new SalesOrderLine();
        $newLine->item = $item;
        $newLine->quantityOrdered = 60; // Increased from 20 to 60

        $salesOrder = new SalesOrder();
        $salesOrder->id = 1;
        $salesOrder->orderNumber = 'SO-TEST-002';
        $salesOrder->lines = new ArrayCollection([$newLine]);

        $previousState = [
            'lines' => [
                [
                    'item' => ['id' => 1],
                    'quantityOrdered' => 20,
                ],
            ],
        ];

        $event = new SalesOrderUpdatedEvent($salesOrder, $previousState);

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

        $this->entityManager->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        // After reversal: committed = 20 - 20 = 0, available = 50 - 0 = 50
        // After new application: 60 ordered, 50 available
        //   committed = 0 + 50 = 50
        //   backordered = 60 - 50 = 10
        //   available = 50 - 50 = 0
        $this->assertEquals(50, $item->quantityCommitted);
        $this->assertEquals(0, $item->quantityAvailable);
        $this->assertEquals(10, $item->quantityBackOrdered);
    }
}

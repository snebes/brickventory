<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\CostLayer;
use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\SalesOrder;
use App\Event\ItemFulfilledEvent;
use App\EventHandler\ItemFulfilledEventHandler;
use App\Repository\CostLayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ItemFulfilledEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private CostLayerRepository $costLayerRepository;
    private ItemFulfilledEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->costLayerRepository = $this->createMock(CostLayerRepository::class);
        $this->handler = new ItemFulfilledEventHandler($this->entityManager, $this->costLayerRepository);
    }

    public function testItemFulfilledEventCreatesEventInStore(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityOnOrder = 0;
        $item->quantityBackOrdered = 0;
        $item->quantityCommitted = 50;

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-123';

        $event = new ItemFulfilledEvent($item, 50, $salesOrder);

        // Mock cost layer repository to return empty (no cost layers)
        $this->costLayerRepository
            ->expects($this->once())
            ->method('findAvailableByItem')
            ->with($item)
            ->willReturn([]);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(2))  // ItemEvent, Item
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
        $this->assertCount(2, $persistedEntities);
        
        // Check ItemEvent was persisted
        $itemEvent = $persistedEntities[0];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent);
        $this->assertEquals('item_fulfilled', $itemEvent->eventType);
        $this->assertEquals(-50, $itemEvent->quantityChange); // Negative for fulfillment
        $this->assertEquals('sales_order', $itemEvent->referenceType);
        
        // Check Item was persisted with updated quantities
        // quantityAvailable = quantityOnHand - quantityCommitted
        $this->assertSame($item, $persistedEntities[1]);
        $this->assertEquals(50, $item->quantityOnHand); // 100 - 50
        $this->assertEquals(0, $item->quantityCommitted); // 50 - 50
        $this->assertEquals(50, $item->quantityAvailable); // 50 - 0
    }

    public function testItemFulfilledEventConsumesCostLayersInFIFOOrder(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityOnOrder = 0;
        $item->quantityCommitted = 50;
        $item->quantityAvailable = 50;

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-FIFO';

        $event = new ItemFulfilledEvent($item, 50, $salesOrder);

        // Create mock cost layers (older to newer for FIFO)
        $costLayer1 = new CostLayer();
        $costLayer1->item = $item;
        $costLayer1->quantityRemaining = 30;
        $costLayer1->unitCost = 5.00;  // Older, cheaper batch
        
        $costLayer2 = new CostLayer();
        $costLayer2->item = $item;
        $costLayer2->quantityRemaining = 70;
        $costLayer2->unitCost = 7.00;  // Newer, more expensive batch

        // Mock cost layer repository to return layers in FIFO order
        $this->costLayerRepository
            ->expects($this->once())
            ->method('findAvailableByItem')
            ->with($item)
            ->willReturn([$costLayer1, $costLayer2]);

        $persistedEntities = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert - FIFO should consume from oldest layer first
        $this->assertEquals(0, $costLayer1->quantityRemaining);  // Fully consumed (30 units)
        $this->assertEquals(50, $costLayer2->quantityRemaining); // Partially consumed (70 - 20 = 50)

        // Check ItemEvent metadata contains COGS information
        $itemEvents = array_filter($persistedEntities, fn($e) => $e instanceof ItemEvent);
        $itemEvent = array_values($itemEvents)[0];
        $metadata = json_decode($itemEvent->metadata, true);
        
        // Expected COGS: 30 units @ $5.00 + 20 units @ $7.00 = $150 + $140 = $290
        $this->assertEquals(290.0, $metadata['cost_of_goods_sold']);
        $this->assertCount(2, $metadata['cost_layers_consumed']);
    }

    public function testItemFulfilledEventWithCommittedQuantity(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityOnOrder = 0;
        $item->quantityCommitted = 30;
        $item->quantityAvailable = 70; // 100 - 30

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-456';

        $event = new ItemFulfilledEvent($item, 30, $salesOrder);

        $this->costLayerRepository
            ->method('findAvailableByItem')
            ->willReturn([]);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(70, $item->quantityOnHand); // 100 - 30
        $this->assertEquals(0, $item->quantityCommitted); // 30 - 30
        $this->assertEquals(70, $item->quantityAvailable); // 70 - 0
    }

    public function testItemFulfilledEventWithNoCommitted(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityOnOrder = 50;
        $item->quantityCommitted = 25;
        $item->quantityAvailable = 75; // 100 - 25

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-789';

        $event = new ItemFulfilledEvent($item, 25, $salesOrder);

        $this->costLayerRepository
            ->method('findAvailableByItem')
            ->willReturn([]);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(75, $item->quantityOnHand); // 100 - 25
        $this->assertEquals(0, $item->quantityCommitted); // 25 - 25
        $this->assertEquals(75, $item->quantityAvailable); // 75 - 0
    }

    public function testItemFulfilledEventWithSingleCostLayer(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 50;
        $item->quantityOnOrder = 0;
        $item->quantityCommitted = 20;

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-SINGLE';

        $event = new ItemFulfilledEvent($item, 20, $salesOrder);

        // Create a single cost layer
        $costLayer = new CostLayer();
        $costLayer->item = $item;
        $costLayer->quantityRemaining = 50;
        $costLayer->unitCost = 10.00;

        $this->costLayerRepository
            ->method('findAvailableByItem')
            ->willReturn([$costLayer]);

        $persistedEntities = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(30, $costLayer->quantityRemaining);  // 50 - 20

        // Check COGS: 20 units @ $10.00 = $200
        $itemEvents = array_filter($persistedEntities, fn($e) => $e instanceof ItemEvent);
        $itemEvent = array_values($itemEvents)[0];
        $metadata = json_decode($itemEvent->metadata, true);
        
        $this->assertEquals(200.0, $metadata['cost_of_goods_sold']);
    }
}

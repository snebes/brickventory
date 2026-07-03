<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\CostLayer;
use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\ItemFulfillment;
use App\Entity\ItemFulfillmentLine;
use App\Entity\OrderEvent;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\ItemFulfillmentCreatedEvent;
use App\EventHandler\ItemFulfillmentCreatedEventHandler;
use App\Repository\CostLayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ItemFulfillmentCreatedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private CostLayerRepository $costLayerRepository;
    private ItemFulfillmentCreatedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->costLayerRepository = $this->createMock(CostLayerRepository::class);
        $this->handler = new ItemFulfillmentCreatedEventHandler($this->entityManager, $this->costLayerRepository);
    }

    public function testFulfillmentCreatedEventUpdatesInventoryAndOrderStatus(): void
    {
        // Arrange - Create item with inventory
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 50;
        $item->quantityAvailable = 50;

        // Create sales order and line
        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-TEST-001';
        $salesOrder->status = SalesOrder::STATUS_PENDING_FULFILLMENT;
        $salesOrder->lines = new ArrayCollection();

        $salesOrderLine = new SalesOrderLine();
        $salesOrderLine->salesOrder = $salesOrder;
        $salesOrderLine->item = $item;
        $salesOrderLine->quantityOrdered = 50;
        $salesOrderLine->quantityCommitted = 50;
        $salesOrderLine->quantityFulfilled = 0;
        
        $salesOrder->lines->add($salesOrderLine);

        // Create fulfillment
        $fulfillment = new ItemFulfillment();
        $fulfillment->salesOrder = $salesOrder;
        $fulfillment->fulfillmentNumber = 'IF-TEST-001';
        $fulfillment->status = ItemFulfillment::STATUS_PICKED;
        $fulfillment->lines = new ArrayCollection();

        $fulfillmentLine = new ItemFulfillmentLine();
        $fulfillmentLine->itemFulfillment = $fulfillment;
        $fulfillmentLine->salesOrderLine = $salesOrderLine;
        $fulfillmentLine->item = $item;
        $fulfillmentLine->quantityFulfilled = 50;

        $fulfillment->lines->add($fulfillmentLine);
        $salesOrder->fulfillments = new ArrayCollection([$fulfillment]);

        $event = new ItemFulfillmentCreatedEvent($fulfillment);

        // Mock cost layer repository to return empty
        $this->costLayerRepository
            ->method('findAvailableByItem')
            ->willReturn([]);

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

        // Assert - Item quantities updated
        $this->assertEquals(50, $item->quantityOnHand); // 100 - 50
        $this->assertEquals(0, $item->quantityCommitted); // 50 - 50
        $this->assertEquals(50, $item->quantityAvailable); // 50 - 0

        // Assert - Sales order line updated
        $this->assertEquals(50, $salesOrderLine->quantityFulfilled);
        $this->assertEquals(0, $salesOrderLine->quantityCommitted);

        // Assert - Sales order status updated to fulfilled
        $this->assertEquals(SalesOrder::STATUS_FULFILLED, $salesOrder->status);

        // Assert - OrderEvent and ItemEvent created
        $orderEvents = array_filter($persistedEntities, fn($e) => $e instanceof OrderEvent);
        $itemEvents = array_filter($persistedEntities, fn($e) => $e instanceof ItemEvent);
        
        $this->assertCount(1, $orderEvents);
        $this->assertCount(1, $itemEvents);
    }

    public function testPartialFulfillmentUpdatesOrderStatusToPartiallyFulfilled(): void
    {
        // Arrange - Create item with inventory
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 50;
        $item->quantityAvailable = 50;

        // Create sales order and line
        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-PARTIAL';
        $salesOrder->status = SalesOrder::STATUS_PENDING_FULFILLMENT;
        $salesOrder->lines = new ArrayCollection();

        $salesOrderLine = new SalesOrderLine();
        $salesOrderLine->salesOrder = $salesOrder;
        $salesOrderLine->item = $item;
        $salesOrderLine->quantityOrdered = 50;
        $salesOrderLine->quantityCommitted = 50;
        $salesOrderLine->quantityFulfilled = 0;
        
        $salesOrder->lines->add($salesOrderLine);

        // Create fulfillment for partial quantity
        $fulfillment = new ItemFulfillment();
        $fulfillment->salesOrder = $salesOrder;
        $fulfillment->fulfillmentNumber = 'IF-PARTIAL';
        $fulfillment->lines = new ArrayCollection();

        $fulfillmentLine = new ItemFulfillmentLine();
        $fulfillmentLine->itemFulfillment = $fulfillment;
        $fulfillmentLine->salesOrderLine = $salesOrderLine;
        $fulfillmentLine->item = $item;
        $fulfillmentLine->quantityFulfilled = 25; // Only partial

        $fulfillment->lines->add($fulfillmentLine);
        $salesOrder->fulfillments = new ArrayCollection([$fulfillment]);

        $event = new ItemFulfillmentCreatedEvent($fulfillment);

        $this->costLayerRepository
            ->method('findAvailableByItem')
            ->willReturn([]);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert - Partial fulfillment status
        $this->assertEquals(25, $salesOrderLine->quantityFulfilled);
        $this->assertEquals(SalesOrder::STATUS_PARTIALLY_FULFILLED, $salesOrder->status);
    }

    public function testFulfillmentConsumesCostLayersInFIFOOrder(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 50;
        $item->quantityAvailable = 50;

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-FIFO';
        $salesOrder->status = SalesOrder::STATUS_PENDING_FULFILLMENT;
        $salesOrder->lines = new ArrayCollection();

        $salesOrderLine = new SalesOrderLine();
        $salesOrderLine->salesOrder = $salesOrder;
        $salesOrderLine->item = $item;
        $salesOrderLine->quantityOrdered = 50;
        $salesOrderLine->quantityCommitted = 50;
        $salesOrderLine->quantityFulfilled = 0;
        
        $salesOrder->lines->add($salesOrderLine);

        $fulfillment = new ItemFulfillment();
        $fulfillment->salesOrder = $salesOrder;
        $fulfillment->fulfillmentNumber = 'IF-FIFO';
        $fulfillment->lines = new ArrayCollection();

        $fulfillmentLine = new ItemFulfillmentLine();
        $fulfillmentLine->itemFulfillment = $fulfillment;
        $fulfillmentLine->salesOrderLine = $salesOrderLine;
        $fulfillmentLine->item = $item;
        $fulfillmentLine->quantityFulfilled = 50;

        $fulfillment->lines->add($fulfillmentLine);
        $salesOrder->fulfillments = new ArrayCollection([$fulfillment]);

        // Create cost layers (FIFO order)
        $costLayer1 = new CostLayer();
        $costLayer1->item = $item;
        $costLayer1->quantityRemaining = 30;
        $costLayer1->unitCost = 5.00;  // Older batch

        $costLayer2 = new CostLayer();
        $costLayer2->item = $item;
        $costLayer2->quantityRemaining = 70;
        $costLayer2->unitCost = 7.00;  // Newer batch

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

        $event = new ItemFulfillmentCreatedEvent($fulfillment);

        // Act
        ($this->handler)($event);

        // Assert - FIFO consumption
        $this->assertEquals(0, $costLayer1->quantityRemaining);  // Fully consumed
        $this->assertEquals(50, $costLayer2->quantityRemaining); // 70 - 20 = 50

        // Check ItemEvent has COGS metadata
        // Expected: 30 @ $5 + 20 @ $7 = $150 + $140 = $290
        $itemEvents = array_filter($persistedEntities, fn($e) => $e instanceof ItemEvent);
        $itemEvent = array_values($itemEvents)[0];
        $metadata = json_decode($itemEvent->metadata, true);
        
        $this->assertEquals(290.0, $metadata['cost_of_goods_sold']);
        $this->assertCount(2, $metadata['cost_layers_consumed']);
    }

    public function testMultiLineFulfillment(): void
    {
        // Arrange - Two items
        $item1 = new Item();
        $item1->quantityOnHand = 50;
        $item1->quantityCommitted = 20;
        $item1->quantityAvailable = 30;

        $item2 = new Item();
        $item2->quantityOnHand = 100;
        $item2->quantityCommitted = 30;
        $item2->quantityAvailable = 70;

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-MULTI';
        $salesOrder->status = SalesOrder::STATUS_PENDING_FULFILLMENT;
        $salesOrder->lines = new ArrayCollection();

        $salesOrderLine1 = new SalesOrderLine();
        $salesOrderLine1->salesOrder = $salesOrder;
        $salesOrderLine1->item = $item1;
        $salesOrderLine1->quantityOrdered = 20;
        $salesOrderLine1->quantityCommitted = 20;
        $salesOrderLine1->quantityFulfilled = 0;

        $salesOrderLine2 = new SalesOrderLine();
        $salesOrderLine2->salesOrder = $salesOrder;
        $salesOrderLine2->item = $item2;
        $salesOrderLine2->quantityOrdered = 30;
        $salesOrderLine2->quantityCommitted = 30;
        $salesOrderLine2->quantityFulfilled = 0;

        $salesOrder->lines->add($salesOrderLine1);
        $salesOrder->lines->add($salesOrderLine2);

        $fulfillment = new ItemFulfillment();
        $fulfillment->salesOrder = $salesOrder;
        $fulfillment->fulfillmentNumber = 'IF-MULTI';
        $fulfillment->lines = new ArrayCollection();

        $fulfillmentLine1 = new ItemFulfillmentLine();
        $fulfillmentLine1->itemFulfillment = $fulfillment;
        $fulfillmentLine1->salesOrderLine = $salesOrderLine1;
        $fulfillmentLine1->item = $item1;
        $fulfillmentLine1->quantityFulfilled = 20;

        $fulfillmentLine2 = new ItemFulfillmentLine();
        $fulfillmentLine2->itemFulfillment = $fulfillment;
        $fulfillmentLine2->salesOrderLine = $salesOrderLine2;
        $fulfillmentLine2->item = $item2;
        $fulfillmentLine2->quantityFulfilled = 30;

        $fulfillment->lines->add($fulfillmentLine1);
        $fulfillment->lines->add($fulfillmentLine2);
        $salesOrder->fulfillments = new ArrayCollection([$fulfillment]);

        $this->costLayerRepository
            ->method('findAvailableByItem')
            ->willReturn([]);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $event = new ItemFulfillmentCreatedEvent($fulfillment);

        // Act
        ($this->handler)($event);

        // Assert - Both items and lines updated
        $this->assertEquals(30, $item1->quantityOnHand);
        $this->assertEquals(0, $item1->quantityCommitted);
        $this->assertEquals(20, $salesOrderLine1->quantityFulfilled);

        $this->assertEquals(70, $item2->quantityOnHand);
        $this->assertEquals(0, $item2->quantityCommitted);
        $this->assertEquals(30, $salesOrderLine2->quantityFulfilled);

        // Order should be fully fulfilled
        $this->assertEquals(SalesOrder::STATUS_FULFILLED, $salesOrder->status);
    }
}

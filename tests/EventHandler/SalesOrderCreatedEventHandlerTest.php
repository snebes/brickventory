<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\OrderEvent;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\SalesOrderCreatedEvent;
use App\EventHandler\SalesOrderCreatedEventHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SalesOrderCreatedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SalesOrderCreatedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new SalesOrderCreatedEventHandler($this->entityManager);
    }

    public function testSalesOrderCreatedEventCreatesEventsInStore(): void
    {
        // Arrange - items with sufficient quantity available
        $item1 = new Item();
        $item1->quantityOnHand = 100;
        $item1->quantityOnOrder = 0;
        $item1->quantityCommitted = 0;
        $item1->quantityAvailable = 100;
        $item1->quantityBackOrdered = 0;

        $item2 = new Item();
        $item2->quantityOnHand = 50;
        $item2->quantityOnOrder = 0;
        $item2->quantityCommitted = 10;
        $item2->quantityAvailable = 40;
        $item2->quantityBackOrdered = 0;

        $line1 = new SalesOrderLine();
        $line1->item = $item1;
        $line1->quantityOrdered = 30;

        $line2 = new SalesOrderLine();
        $line2->item = $item2;
        $line2->quantityOrdered = 20;

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-TEST-001';
        $salesOrder->lines = new ArrayCollection([$line1, $line2]);

        $event = new SalesOrderCreatedEvent($salesOrder);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(5)) // 1 OrderEvent + 2 ItemEvents + 2 Items
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
        $this->assertCount(5, $persistedEntities);
        
        // Check OrderEvent was created
        $this->assertInstanceOf(OrderEvent::class, $persistedEntities[0]);
        
        // Check first ItemEvent
        $itemEvent1 = $persistedEntities[1];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent1);
        $this->assertEquals('sales_order_created', $itemEvent1->eventType);
        $this->assertEquals(-30, $itemEvent1->quantityChange); // Negative because committed
        $this->assertEquals('sales_order', $itemEvent1->referenceType);
        
        // Check metadata includes committed/backordered breakdown
        $metadata1 = json_decode($itemEvent1->metadata, true);
        $this->assertEquals(30, $metadata1['quantity_committed']);
        $this->assertEquals(0, $metadata1['quantity_backordered']);
        
        // Check first Item was updated - no backorder since 30 <= 100
        $this->assertEquals($item1, $persistedEntities[2]);
        $this->assertEquals(30, $item1->quantityCommitted);
        $this->assertEquals(70, $item1->quantityAvailable); // 100 - 30
        $this->assertEquals(0, $item1->quantityBackOrdered);
        
        // Check second ItemEvent
        $itemEvent2 = $persistedEntities[3];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent2);
        $this->assertEquals(-20, $itemEvent2->quantityChange);
        
        // Check metadata for second item
        $metadata2 = json_decode($itemEvent2->metadata, true);
        $this->assertEquals(20, $metadata2['quantity_committed']);
        $this->assertEquals(0, $metadata2['quantity_backordered']);
        
        // Check second Item was updated - no backorder since 20 <= 40
        $this->assertEquals($item2, $persistedEntities[4]);
        $this->assertEquals(30, $item2->quantityCommitted); // 10 + 20
        $this->assertEquals(20, $item2->quantityAvailable); // 50 - 30
        $this->assertEquals(0, $item2->quantityBackOrdered);
    }

    public function testSalesOrderCreatedWithBackorderWhenQuantityExceedsAvailable(): void
    {
        // Arrange - item with insufficient quantity available
        $item = new Item();
        $item->quantityOnHand = 20;
        $item->quantityOnOrder = 0;
        $item->quantityCommitted = 5;
        $item->quantityAvailable = 15; // Only 15 available
        $item->quantityBackOrdered = 0;

        $line = new SalesOrderLine();
        $line->item = $item;
        $line->quantityOrdered = 50; // Order 50, but only 15 available

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-TEST-BACKORDER';
        $salesOrder->lines = new ArrayCollection([$line]);

        $event = new SalesOrderCreatedEvent($salesOrder);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(3)) // 1 OrderEvent + 1 ItemEvent + 1 Item
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
        $this->assertCount(3, $persistedEntities);
        
        // Check ItemEvent
        $itemEvent = $persistedEntities[1];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent);
        $this->assertEquals(-50, $itemEvent->quantityChange);
        
        // Check metadata includes committed/backordered breakdown
        $metadata = json_decode($itemEvent->metadata, true);
        $this->assertEquals(15, $metadata['quantity_committed']); // Only 15 available to commit
        $this->assertEquals(35, $metadata['quantity_backordered']); // 50 - 15 = 35 backordered
        
        // Check Item was updated with backorder
        $this->assertEquals($item, $persistedEntities[2]);
        $this->assertEquals(20, $item->quantityCommitted); // 5 + 15 = 20
        $this->assertEquals(0, $item->quantityAvailable); // 20 - 20 = 0
        $this->assertEquals(35, $item->quantityBackOrdered); // 35 backordered
    }

    public function testSalesOrderCreatedWithZeroAvailableAllBackordered(): void
    {
        // Arrange - item with no quantity available (all committed)
        $item = new Item();
        $item->quantityOnHand = 30;
        $item->quantityOnOrder = 0;
        $item->quantityCommitted = 30;
        $item->quantityAvailable = 0; // None available
        $item->quantityBackOrdered = 0;

        $line = new SalesOrderLine();
        $line->item = $item;
        $line->quantityOrdered = 25; // All 25 will be backordered

        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = 'SO-TEST-ALL-BACKORDER';
        $salesOrder->lines = new ArrayCollection([$line]);

        $event = new SalesOrderCreatedEvent($salesOrder);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(3)) // 1 OrderEvent + 1 ItemEvent + 1 Item
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
        $metadata = json_decode($persistedEntities[1]->metadata, true);
        $this->assertEquals(0, $metadata['quantity_committed']); // Nothing to commit
        $this->assertEquals(25, $metadata['quantity_backordered']); // All backordered
        
        // Check Item quantities
        $this->assertEquals(30, $item->quantityCommitted); // Unchanged
        $this->assertEquals(0, $item->quantityAvailable); // Still 0
        $this->assertEquals(25, $item->quantityBackOrdered); // All 25 backordered
    }
}

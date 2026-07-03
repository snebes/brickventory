<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\CostLayer;
use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\PurchaseOrder;
use App\Event\ItemReceivedEvent;
use App\EventHandler\ItemReceivedEventHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ItemReceivedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ItemReceivedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new ItemReceivedEventHandler($this->entityManager);
    }

    public function testItemReceivedEventCreatesEventAndCostLayerInStore(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 0;
        $item->quantityOnOrder = 100;
        $item->quantityBackOrdered = 0;
        $item->quantityCommitted = 0;

        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-123';
        $purchaseOrder->reference = 'Test Order';

        $unitCost = 5.99;
        $event = new ItemReceivedEvent($item, 50, $purchaseOrder, $unitCost);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(3))  // ItemEvent, CostLayer, Item
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
        
        // Check ItemEvent was persisted
        $itemEvent = $persistedEntities[0];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent);
        $this->assertEquals('item_received', $itemEvent->eventType);
        $this->assertEquals(50, $itemEvent->quantityChange);
        $this->assertEquals('purchase_order', $itemEvent->referenceType);

        // Check CostLayer was persisted
        $costLayer = $persistedEntities[1];
        $this->assertInstanceOf(CostLayer::class, $costLayer);
        $this->assertSame($item, $costLayer->item);
        $this->assertEquals(50, $costLayer->quantityReceived);
        $this->assertEquals(50, $costLayer->quantityRemaining);
        $this->assertEquals($unitCost, $costLayer->unitCost);
        
        // Check Item was persisted with updated quantities
        // quantityAvailable = quantityOnHand - quantityCommitted
        $this->assertSame($item, $persistedEntities[2]);
        $this->assertEquals(50, $item->quantityOnHand);
        $this->assertEquals(50, $item->quantityOnOrder);
        $this->assertEquals(50, $item->quantityAvailable); // 50 - 0
    }

    public function testItemReceivedEventUpdatesInventoryCorrectly(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 20;
        $item->quantityOnOrder = 100;
        $item->quantityBackOrdered = 10;
        $item->quantityCommitted = 5;
        $item->quantityAvailable = 15; // 20 - 5

        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-456';

        $unitCost = 10.50;
        $event = new ItemReceivedEvent($item, 30, $purchaseOrder, $unitCost);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(50, $item->quantityOnHand); // 20 + 30
        $this->assertEquals(70, $item->quantityOnOrder); // 100 - 30
        $this->assertEquals(10, $item->quantityBackOrdered); // unchanged
        $this->assertEquals(5, $item->quantityCommitted); // unchanged
        $this->assertEquals(45, $item->quantityAvailable); // 50 - 5
    }

    public function testItemReceivedEventWithZeroCostCreatesValidCostLayer(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 0;
        $item->quantityOnOrder = 50;
        $item->quantityCommitted = 0;

        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-FREE';

        // Free items have zero cost
        $event = new ItemReceivedEvent($item, 25, $purchaseOrder, 0.0);

        $persistedEntities = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert - CostLayer should still be created even with zero cost
        $costLayers = array_filter($persistedEntities, fn($e) => $e instanceof CostLayer);
        $this->assertCount(1, $costLayers);
        
        $costLayer = array_values($costLayers)[0];
        $this->assertEquals(0.0, $costLayer->unitCost);
        $this->assertEquals(25, $costLayer->quantityReceived);
    }
}

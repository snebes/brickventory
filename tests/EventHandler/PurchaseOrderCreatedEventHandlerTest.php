<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use App\EventHandler\PurchaseOrderCreatedEventHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PurchaseOrderCreatedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private PurchaseOrderCreatedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new PurchaseOrderCreatedEventHandler($this->entityManager);
    }

    public function testPurchaseOrderCreatedEventCreatesEventsInStore(): void
    {
        // Arrange
        $item1 = new Item();
        $item1->quantityOnHand = 10;
        $item1->quantityOnOrder = 0;
        $item1->quantityBackOrdered = 0;
        $item1->quantityAvailable = 10;

        $item2 = new Item();
        $item2->quantityOnHand = 20;
        $item2->quantityOnOrder = 0;
        $item2->quantityBackOrdered = 5;
        $item2->quantityAvailable = 15;

        $line1 = new PurchaseOrderLine();
        $line1->item = $item1;
        $line1->quantityOrdered = 50;

        $line2 = new PurchaseOrderLine();
        $line2->item = $item2;
        $line2->quantityOrdered = 30;

        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-TEST-001';
        $purchaseOrder->reference = 'Test Reference';
        $purchaseOrder->lines = new ArrayCollection([$line1, $line2]);

        $event = new PurchaseOrderCreatedEvent($purchaseOrder);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(4)) // 2 ItemEvents + 2 Items
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
        $this->assertCount(4, $persistedEntities);
        
        // Check first ItemEvent
        $itemEvent1 = $persistedEntities[0];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent1);
        $this->assertEquals('purchase_order_created', $itemEvent1->eventType);
        $this->assertEquals(50, $itemEvent1->quantityChange);
        $this->assertEquals('purchase_order', $itemEvent1->referenceType);
        
        // Check first Item was updated
        $this->assertEquals($item1, $persistedEntities[1]);
        $this->assertEquals(50, $item1->quantityOnOrder);
        $this->assertEquals(60, $item1->quantityAvailable); // 10 + 50 - 0
        
        // Check second ItemEvent
        $itemEvent2 = $persistedEntities[2];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent2);
        $this->assertEquals(30, $itemEvent2->quantityChange);
        
        // Check second Item was updated
        $this->assertEquals($item2, $persistedEntities[3]);
        $this->assertEquals(30, $item2->quantityOnOrder);
        $this->assertEquals(45, $item2->quantityAvailable); // 20 + 30 - 5
    }
}

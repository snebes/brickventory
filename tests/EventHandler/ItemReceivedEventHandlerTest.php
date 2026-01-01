<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

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

    public function testItemReceivedEventCreatesEventInStore(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 0;
        $item->quantityOnOrder = 100;
        $item->quantityBackOrdered = 0;

        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-123';
        $purchaseOrder->reference = 'Test Order';

        $event = new ItemReceivedEvent($item, 50, $purchaseOrder);

        $persistedEntities = [];
        $this->entityManager
            ->expects($this->exactly(2))
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
        $this->assertEquals('item_received', $itemEvent->eventType);
        $this->assertEquals(50, $itemEvent->quantityChange);
        $this->assertEquals('purchase_order', $itemEvent->referenceType);
        
        // Check Item was persisted with updated quantities
        $this->assertEquals($item, $persistedEntities[1]);
        $this->assertEquals(50, $item->quantityOnHand);
        $this->assertEquals(50, $item->quantityOnOrder);
        $this->assertEquals(50, $item->quantityAvailable);
    }

    public function testItemReceivedEventUpdatesInventoryCorrectly(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 20;
        $item->quantityOnOrder = 100;
        $item->quantityBackOrdered = 10;
        $item->quantityAvailable = 110; // 20 + 100 - 10

        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-456';

        $event = new ItemReceivedEvent($item, 30, $purchaseOrder);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(50, $item->quantityOnHand); // 20 + 30
        $this->assertEquals(70, $item->quantityOnOrder); // 100 - 30
        $this->assertEquals(10, $item->quantityBackOrdered); // unchanged
        $this->assertEquals(110, $item->quantityAvailable); // 50 + 70 - 10
    }
}

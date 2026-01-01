<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\SalesOrder;
use App\Event\ItemFulfilledEvent;
use App\EventHandler\ItemFulfilledEventHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ItemFulfilledEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ItemFulfilledEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new ItemFulfilledEventHandler($this->entityManager);
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
        $this->assertEquals('item_fulfilled', $itemEvent->eventType);
        $this->assertEquals(-50, $itemEvent->quantityChange); // Negative for fulfillment
        $this->assertEquals('sales_order', $itemEvent->referenceType);
        
        // Check Item was persisted with updated quantities
        // quantityAvailable = quantityOnHand - quantityCommitted
        $this->assertEquals($item, $persistedEntities[1]);
        $this->assertEquals(50, $item->quantityOnHand); // 100 - 50
        $this->assertEquals(0, $item->quantityCommitted); // 50 - 50
        $this->assertEquals(50, $item->quantityAvailable); // 50 - 0
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

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(75, $item->quantityOnHand); // 100 - 25
        $this->assertEquals(0, $item->quantityCommitted); // 25 - 25
        $this->assertEquals(75, $item->quantityAvailable); // 75 - 0
    }
}

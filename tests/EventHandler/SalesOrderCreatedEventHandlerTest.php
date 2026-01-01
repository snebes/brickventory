<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\Item;
use App\Entity\ItemEvent;
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
        // Arrange
        $item1 = new Item();
        $item1->quantityOnHand = 100;
        $item1->quantityOnOrder = 0;
        $item1->quantityCommitted = 0;
        $item1->quantityAvailable = 100;

        $item2 = new Item();
        $item2->quantityOnHand = 50;
        $item2->quantityOnOrder = 0;
        $item2->quantityCommitted = 10;
        $item2->quantityAvailable = 40;

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
        $this->assertEquals('sales_order_created', $itemEvent1->eventType);
        $this->assertEquals(-30, $itemEvent1->quantityChange); // Negative because committed
        $this->assertEquals('sales_order', $itemEvent1->referenceType);
        
        // Check first Item was updated
        $this->assertEquals($item1, $persistedEntities[1]);
        $this->assertEquals(30, $item1->quantityCommitted);
        $this->assertEquals(70, $item1->quantityAvailable); // 100 - 30
        
        // Check second ItemEvent
        $itemEvent2 = $persistedEntities[2];
        $this->assertInstanceOf(ItemEvent::class, $itemEvent2);
        $this->assertEquals(-20, $itemEvent2->quantityChange);
        
        // Check second Item was updated
        $this->assertEquals($item2, $persistedEntities[3]);
        $this->assertEquals(30, $item2->quantityCommitted); // 10 + 20
        $this->assertEquals(20, $item2->quantityAvailable); // 50 - 30
    }
}

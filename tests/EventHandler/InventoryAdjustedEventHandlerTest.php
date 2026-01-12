<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\InventoryAdjustment;
use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Event\InventoryAdjustedEvent;
use App\EventHandler\InventoryAdjustedEventHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InventoryAdjustedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private InventoryAdjustedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new InventoryAdjustedEventHandler($this->entityManager);
    }

    public function testPositiveAdjustmentIncreasesQuantityOnHand(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 10;
        $item->quantityAvailable = 90;

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-001';
        $adjustment->reason = 'physical_count';

        $event = new InventoryAdjustedEvent($item, 50, $adjustment);

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
        $this->assertEquals('inventory_adjusted', $itemEvent->eventType);
        $this->assertEquals(50, $itemEvent->quantityChange);
        $this->assertEquals('inventory_adjustment', $itemEvent->referenceType);
        
        // Check Item quantities were updated
        $this->assertEquals(150, $item->quantityOnHand); // 100 + 50
        $this->assertEquals(10, $item->quantityCommitted); // unchanged
        $this->assertEquals(140, $item->quantityAvailable); // 150 - 10
    }

    public function testNegativeAdjustmentDecreasesQuantityOnHand(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 10;
        $item->quantityAvailable = 90;

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-002';
        $adjustment->reason = 'damaged';

        $event = new InventoryAdjustedEvent($item, -30, $adjustment);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertEquals(70, $item->quantityOnHand); // 100 - 30
        $this->assertEquals(10, $item->quantityCommitted); // unchanged
        $this->assertEquals(60, $item->quantityAvailable); // 70 - 10
    }

    public function testAdjustmentCreatesEventWithCorrectMetadata(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 50;
        $item->quantityCommitted = 0;

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-003';
        $adjustment->reason = 'correction';
        $adjustment->memo = 'Test memo';

        $event = new InventoryAdjustedEvent($item, 25, $adjustment);

        $persistedItemEvent = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persistedItemEvent) {
                if ($entity instanceof ItemEvent) {
                    $persistedItemEvent = $entity;
                }
            });

        $this->entityManager->method('flush');

        // Act
        ($this->handler)($event);

        // Assert
        $this->assertNotNull($persistedItemEvent);
        $this->assertEquals($item, $persistedItemEvent->item);
        $this->assertEquals('inventory_adjusted', $persistedItemEvent->eventType);
        $this->assertEquals(25, $persistedItemEvent->quantityChange);
        
        $metadata = json_decode($persistedItemEvent->metadata, true);
        $this->assertEquals('ADJ-003', $metadata['adjustment_number']);
        $this->assertEquals('correction', $metadata['reason']);
        $this->assertEquals('Test memo', $metadata['memo']);
    }
}

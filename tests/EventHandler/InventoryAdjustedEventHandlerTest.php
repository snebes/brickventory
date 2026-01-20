<?php

declare(strict_types=1);

namespace App\Tests\EventHandler;

use App\Entity\InventoryAdjustment;
use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Entity\Location;
use App\Event\InventoryAdjustedEvent;
use App\EventHandler\InventoryAdjustedEventHandler;
use App\Service\InventoryBalanceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InventoryAdjustedEventHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private InventoryBalanceService $inventoryBalanceService;
    private InventoryAdjustedEventHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->inventoryBalanceService = $this->createMock(InventoryBalanceService::class);
        $this->handler = new InventoryAdjustedEventHandler($this->entityManager, $this->inventoryBalanceService);
    }

    private function createTestLocation(): Location
    {
        $location = new Location();
        // Use reflection to set the id since it's normally managed by Doctrine
        $reflection = new \ReflectionClass($location);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($location, 1);
        
        $location->locationCode = 'WH001';
        $location->locationName = 'Main Warehouse';
        $location->active = true;
        
        return $location;
    }

    public function testPositiveAdjustmentIncreasesQuantityOnHand(): void
    {
        // Arrange
        $item = new Item();
        $item->quantityOnHand = 100;
        $item->quantityCommitted = 10;
        $item->quantityAvailable = 90;
        
        // Use reflection to set the id since it's normally managed by Doctrine
        $reflection = new \ReflectionClass($item);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($item, 1);

        $location = $this->createTestLocation();

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-001';
        $adjustment->reason = 'physical_count';
        $adjustment->location = $location;

        $event = new InventoryAdjustedEvent($item, 50, $adjustment);

        // Expect inventory balance service to be called
        $this->inventoryBalanceService
            ->expects($this->once())
            ->method('updateBalance')
            ->with(1, 1, 50, 'adjustment_increase', null);

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
        
        // Check Item quantities were updated (backward compatibility)
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
        
        // Use reflection to set the id
        $reflection = new \ReflectionClass($item);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($item, 1);

        $location = $this->createTestLocation();

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-002';
        $adjustment->reason = 'damaged';
        $adjustment->location = $location;

        $event = new InventoryAdjustedEvent($item, -30, $adjustment);

        // Expect inventory balance service to be called with decrease
        $this->inventoryBalanceService
            ->expects($this->once())
            ->method('updateBalance')
            ->with(1, 1, -30, 'adjustment_decrease', null);

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
        
        // Use reflection to set the id
        $reflection = new \ReflectionClass($item);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($item, 1);

        $location = $this->createTestLocation();

        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-003';
        $adjustment->reason = 'correction';
        $adjustment->memo = 'Test memo';
        $adjustment->location = $location;

        $event = new InventoryAdjustedEvent($item, 25, $adjustment);

        $this->inventoryBalanceService->method('updateBalance');

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
        $this->assertEquals(1, $metadata['location_id']);
        $this->assertEquals('WH001', $metadata['location_code']);
        $this->assertEquals('Main Warehouse', $metadata['location_name']);
    }
}

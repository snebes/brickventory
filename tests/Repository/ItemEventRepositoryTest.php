<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Item;
use App\Entity\ItemEvent;
use App\Repository\ItemEventRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class ItemEventRepositoryTest extends TestCase
{
    public function testCalculateQuantitiesFromEvents(): void
    {
        // Arrange
        $item = new Item();
        $item->itemId = 'TEST-ITEM-001';
        
        // Create mock events
        $event1 = $this->createMockEvent('purchase_order_created', 100);
        $event2 = $this->createMockEvent('item_received', 100);
        $event3 = $this->createMockEvent('item_fulfilled', -30);
        $event4 = $this->createMockEvent('item_fulfilled', -20);
        
        $mockEvents = [$event1, $event2, $event3, $event4];
        
        // Create mock query
        $mockQuery = $this->createMock(AbstractQuery::class);
        $mockQuery->method('getResult')->willReturn($mockEvents);
        
        // Create mock query builder
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('where')->willReturnSelf();
        $mockQueryBuilder->method('setParameter')->willReturnSelf();
        $mockQueryBuilder->method('orderBy')->willReturnSelf();
        $mockQueryBuilder->method('getQuery')->willReturn($mockQuery);
        
        // Create mock manager registry
        $mockRegistry = $this->createMock(ManagerRegistry::class);
        
        // Create repository with partial mock
        $repository = $this->getMockBuilder(ItemEventRepository::class)
            ->setConstructorArgs([$mockRegistry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        
        $repository->method('createQueryBuilder')->willReturn($mockQueryBuilder);
        
        // Act
        $quantities = $repository->calculateQuantitiesFromEvents($item);
        
        // Assert
        $this->assertEquals(50, $quantities['quantityOnHand']); // 100 - 30 - 20
        $this->assertEquals(100, $quantities['quantityOnOrder']); // 100 from purchase order
    }
    
    public function testCalculateQuantitiesWithNoEvents(): void
    {
        // Arrange
        $item = new Item();
        $item->itemId = 'EMPTY-ITEM';
        
        $mockQuery = $this->createMock(AbstractQuery::class);
        $mockQuery->method('getResult')->willReturn([]);
        
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder->method('where')->willReturnSelf();
        $mockQueryBuilder->method('setParameter')->willReturnSelf();
        $mockQueryBuilder->method('orderBy')->willReturnSelf();
        $mockQueryBuilder->method('getQuery')->willReturn($mockQuery);
        
        $mockRegistry = $this->createMock(ManagerRegistry::class);
        
        $repository = $this->getMockBuilder(ItemEventRepository::class)
            ->setConstructorArgs([$mockRegistry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
        
        $repository->method('createQueryBuilder')->willReturn($mockQueryBuilder);
        
        // Act
        $quantities = $repository->calculateQuantitiesFromEvents($item);
        
        // Assert
        $this->assertEquals(0, $quantities['quantityOnHand']);
        $this->assertEquals(0, $quantities['quantityOnOrder']);
    }
    
    private function createMockEvent(string $eventType, int $quantityChange): ItemEvent
    {
        $event = $this->createMock(ItemEvent::class);
        $event->eventType = $eventType;
        $event->quantityChange = $quantityChange;
        return $event;
    }
}

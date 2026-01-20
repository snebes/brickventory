<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\PurchaseOrder;
use App\Repository\PurchaseOrderRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class PurchaseOrderRepositoryTest extends TestCase
{
    public function testGetNextOrderNumberWithNoExistingOrders(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(PurchaseOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - first order should be PO001
        $this->assertEquals('PO001', $orderNumber);
    }

    public function testGetNextOrderNumberWithExistingOrders(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        // Simulate existing orders PO001, PO002, PO005
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['orderNumber' => 'PO005'],
                ['orderNumber' => 'PO002'],
                ['orderNumber' => 'PO001'],
            ]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(PurchaseOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - next order should be PO006
        $this->assertEquals('PO006', $orderNumber);
    }

    public function testGetNextOrderNumberIgnoresNonMatchingFormats(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        // Simulate existing orders including old format orders
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['orderNumber' => 'PO-20260120143800'], // Old timestamp format
                ['orderNumber' => 'PO003'],
                ['orderNumber' => 'PO001'],
                ['orderNumber' => 'PO-ABC'], // Invalid format
            ]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(PurchaseOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - next order should be PO004 (PO003 is the highest valid format)
        $this->assertEquals('PO004', $orderNumber);
    }

    public function testGetNextOrderNumberWithLargeNumber(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        // Simulate existing order with a large number (more than 3 digits)
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['orderNumber' => 'PO1000'],
            ]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(PurchaseOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - next order should be PO1001 (no padding needed when > 3 digits)
        $this->assertEquals('PO1001', $orderNumber);
    }
}

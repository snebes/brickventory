<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\SalesOrder;
use App\Repository\SalesOrderRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class SalesOrderRepositoryTest extends TestCase
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
        
        $repository = $this->createPartialMock(SalesOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - first order should be SO001
        $this->assertEquals('SO001', $orderNumber);
    }

    public function testGetNextOrderNumberWithExistingOrders(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        // Simulate existing orders SO001, SO002, SO005
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([
                ['orderNumber' => 'SO005'],
                ['orderNumber' => 'SO002'],
                ['orderNumber' => 'SO001'],
            ]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(SalesOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - next order should be SO006
        $this->assertEquals('SO006', $orderNumber);
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
                ['orderNumber' => 'SO-20260120143800-1234'], // Old timestamp format
                ['orderNumber' => 'SO003'],
                ['orderNumber' => 'SO001'],
                ['orderNumber' => 'SO-ABC'], // Invalid format
            ]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(SalesOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - next order should be SO004 (SO003 is the highest valid format)
        $this->assertEquals('SO004', $orderNumber);
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
                ['orderNumber' => 'SO1000'],
            ]);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $repository = $this->createPartialMock(SalesOrderRepository::class, ['createQueryBuilder']);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        // Act
        $orderNumber = $repository->getNextOrderNumber();
        
        // Assert - next order should be SO1001 (no padding needed when > 3 digits)
        $this->assertEquals('SO1001', $orderNumber);
    }
}

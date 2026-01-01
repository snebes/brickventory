<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CreatePurchaseOrderCommand;
use App\Entity\Item;
use App\Entity\ItemCategory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;

class CreatePurchaseOrderCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private CreatePurchaseOrderCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->command = new CreatePurchaseOrderCommand($this->entityManager, $this->eventDispatcher);
    }

    public function testFindItemByItemId(): void
    {
        // Arrange
        $category = new ItemCategory();
        $category->name = 'Test Category';

        $item = new Item();
        $item->itemId = 'ITEM-001';
        $item->itemName = 'Test Item';
        $item->category = $category;
        $item->elementIds = 'SKU-001,SKU-002';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['itemId' => 'ITEM-001'])
            ->willReturn($item);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Item::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findItemByIdentifier', ['ITEM-001']);

        // Assert
        $this->assertSame($item, $result);
    }

    public function testFindItemByDatabaseId(): void
    {
        // Arrange - test backward compatibility with database ID
        $category = new ItemCategory();
        $category->name = 'Test Category';

        $item = new Item();
        $item->itemId = 'ITEM-001';
        $item->itemName = 'Test Item';
        $item->category = $category;
        $item->elementIds = 'SKU-001,SKU-002';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 123])
            ->willReturn($item);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Item::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findItemByIdentifier', ['123']);

        // Assert
        $this->assertSame($item, $result);
    }

    public function testFindItemByElementId(): void
    {
        // Arrange
        $category = new ItemCategory();
        $category->name = 'Test Category';

        $item = new Item();
        $item->itemId = 'ITEM-001';
        $item->itemName = 'Test Item';
        $item->category = $category;
        $item->elementIds = 'SKU-001, SKU-002, SKU-003';

        $repository = $this->createMock(EntityRepository::class);
        
        // First call to findOneBy returns null (not found by itemId)
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['itemId' => 'SKU-002'])
            ->willReturn(null);

        // Setup query builder for elementIds search
        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$item]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('i')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->with(Item::class)
            ->willReturn($repository);

        // Act - using non-numeric identifier so it skips database ID lookup
        $result = $this->invokePrivateMethod($this->command, 'findItemByIdentifier', ['SKU-002']);

        // Assert
        $this->assertSame($item, $result);
    }

    public function testFindItemNotFound(): void
    {
        // Arrange
        $repository = $this->createMock(EntityRepository::class);
        
        // First call to findOneBy returns null
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['itemId' => 'NONEXISTENT'])
            ->willReturn(null);

        // Setup query builder for elementIds search
        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]); // No results

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('i')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->any())
            ->method('getRepository')
            ->with(Item::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findItemByIdentifier', ['NONEXISTENT']);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

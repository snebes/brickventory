<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ReceiveItemCommand;
use App\Entity\PurchaseOrder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;

class ReceiveItemCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private ReceiveItemCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->command = new ReceiveItemCommand($this->entityManager, $this->eventDispatcher);
    }

    public function testFindPurchaseOrderById(): void
    {
        // Arrange
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-001';
        $purchaseOrder->reference = 'Test Reference';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 123])
            ->willReturn($purchaseOrder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(PurchaseOrder::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findPurchaseOrderByIdentifier', ['123']);

        // Assert
        $this->assertSame($purchaseOrder, $result);
    }

    public function testFindPurchaseOrderByReference(): void
    {
        // Arrange
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-001';
        $purchaseOrder->reference = 'Test Reference';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => 'Test Reference'])
            ->willReturn($purchaseOrder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(PurchaseOrder::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findPurchaseOrderByIdentifier', ['Test Reference']);

        // Assert
        $this->assertSame($purchaseOrder, $result);
    }

    public function testFindPurchaseOrderByNumericIdFallsBackToReference(): void
    {
        // Arrange
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = 'PO-001';
        $purchaseOrder->reference = '456';

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function ($criteria) use ($purchaseOrder) {
                // First call with id returns null, second call with reference returns the order
                if (isset($criteria['id'])) {
                    return null;
                }
                if (isset($criteria['reference'])) {
                    return $purchaseOrder;
                }
                return null;
            });

        $this->entityManager->expects($this->exactly(2))
            ->method('getRepository')
            ->with(PurchaseOrder::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findPurchaseOrderByIdentifier', ['456']);

        // Assert
        $this->assertSame($purchaseOrder, $result);
    }

    public function testFindPurchaseOrderNotFound(): void
    {
        // Arrange
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => 'NONEXISTENT'])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(PurchaseOrder::class)
            ->willReturn($repository);

        // Act
        $result = $this->invokePrivateMethod($this->command, 'findPurchaseOrderByIdentifier', ['NONEXISTENT']);

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

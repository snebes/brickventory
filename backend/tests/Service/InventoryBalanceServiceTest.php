<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\InventoryBalance;
use App\Entity\Item;
use App\Entity\Location;
use App\Repository\InventoryBalanceRepository;
use App\Repository\ItemRepository;
use App\Repository\LocationRepository;
use App\Service\InventoryBalanceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InventoryBalanceService.
 */
class InventoryBalanceServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private InventoryBalanceRepository|MockObject $balanceRepository;
    private ItemRepository|MockObject $itemRepository;
    private LocationRepository|MockObject $locationRepository;
    private InventoryBalanceService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->balanceRepository = $this->createMock(InventoryBalanceRepository::class);
        $this->itemRepository = $this->createMock(ItemRepository::class);
        $this->locationRepository = $this->createMock(LocationRepository::class);

        $this->service = new InventoryBalanceService(
            $this->entityManager,
            $this->balanceRepository,
            $this->itemRepository,
            $this->locationRepository
        );
    }

    /**
     * Test that updateBalance works without requiring a transaction.
     * This is important because event handlers may not have an active transaction.
     */
    public function testUpdateBalanceWorksWithoutTransaction(): void
    {
        // Create mock entities
        $item = $this->createMock(Item::class);
        $item->method('__get')->with('id')->willReturn(1);

        $location = $this->createMock(Location::class);
        $location->method('__get')->with('id')->willReturn(1);

        $balance = new InventoryBalance();
        $balance->item = $item;
        $balance->location = $location;

        // Setup repository mocks
        $this->itemRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($item);

        $this->locationRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($location);

        $this->balanceRepository
            ->expects($this->once())
            ->method('findOrCreateBalance')
            ->with($item, $location, null)
            ->willReturn($balance);

        // Entity manager should NOT call lock() - this was the bug
        $this->entityManager
            ->expects($this->never())
            ->method('lock');

        // Call the method - this should not throw TransactionRequiredException
        $result = $this->service->updateBalance(1, 1, 10, 'order');

        $this->assertSame($balance, $result);
        $this->assertEquals(10, $balance->quantityOnOrder);
    }

    /**
     * Test different transaction types update correct quantities.
     */
    public function testUpdateBalanceTransactionTypes(): void
    {
        $item = $this->createMock(Item::class);
        $location = $this->createMock(Location::class);
        $balance = new InventoryBalance();
        $balance->item = $item;
        $balance->location = $location;

        $this->itemRepository->method('find')->willReturn($item);
        $this->locationRepository->method('find')->willReturn($location);
        $this->balanceRepository->method('findOrCreateBalance')->willReturn($balance);

        // Test 'order' type
        $this->service->updateBalance(1, 1, 5, 'order');
        $this->assertEquals(5, $balance->quantityOnOrder);

        // Test 'receipt' type
        $balance2 = new InventoryBalance();
        $balance2->item = $item;
        $balance2->location = $location;
        $this->balanceRepository->method('findOrCreateBalance')->willReturn($balance2);

        // Reset mock for new test
        $this->balanceRepository = $this->createMock(InventoryBalanceRepository::class);
        $this->balanceRepository->method('findOrCreateBalance')->willReturn($balance2);

        $service2 = new InventoryBalanceService(
            $this->entityManager,
            $this->balanceRepository,
            $this->itemRepository,
            $this->locationRepository
        );

        $service2->updateBalance(1, 1, 10, 'receipt');
        $this->assertEquals(10, $balance2->quantityOnHand);
    }

    /**
     * Test getBalance returns null for non-existent item.
     */
    public function testGetBalanceReturnsNullForNonExistentItem(): void
    {
        $this->itemRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->getBalance(999, 1);

        $this->assertNull($result);
    }

    /**
     * Test createBalance throws exception for non-existent item.
     */
    public function testCreateBalanceThrowsExceptionForNonExistentItem(): void
    {
        $this->itemRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item with ID 999 not found');

        $this->service->createBalance(999, 1);
    }

    /**
     * Test createBalance throws exception for non-existent location.
     */
    public function testCreateBalanceThrowsExceptionForNonExistentLocation(): void
    {
        $item = $this->createMock(Item::class);

        $this->itemRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($item);

        $this->locationRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Location with ID 999 not found');

        $this->service->createBalance(1, 999);
    }
}

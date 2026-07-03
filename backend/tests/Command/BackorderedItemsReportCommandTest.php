<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BackorderedItemsReportCommand;
use App\Entity\Item;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class BackorderedItemsReportCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private BackorderedItemsReportCommand $command;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->command = new BackorderedItemsReportCommand($this->entityManager);
    }

    public function testGetBackorderedItemsReturnsCorrectData(): void
    {
        // Arrange
        $item1 = new Item();
        $item1->itemId = 'ITEM-001';
        $item1->itemName = 'Test Item 1';
        $item1->quantityAvailable = 5;
        $item1->quantityOnOrder = 10;
        $item1->quantityBackOrdered = 3;

        $item2 = new Item();
        $item2->itemId = 'ITEM-002';
        $item2->itemName = 'Test Item 2';
        $item2->quantityAvailable = 0;
        $item2->quantityOnOrder = 5;
        $item2->quantityBackOrdered = 7;

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$item1, $item2]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('i.quantityBackOrdered > 0')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('i.itemId', 'ASC')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('i')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Item::class)
            ->willReturn($repository);

        // Act
        $result = $this->command->getBackorderedItems();

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('ITEM-001', $result[0]['itemNumber']);
        $this->assertEquals('Test Item 1', $result[0]['name']);
        $this->assertEquals(5, $result[0]['quantityAvailable']);
        $this->assertEquals(10, $result[0]['quantityOnOrder']);
        $this->assertEquals(3, $result[0]['quantityBackordered']);

        $this->assertEquals('ITEM-002', $result[1]['itemNumber']);
        $this->assertEquals('Test Item 2', $result[1]['name']);
        $this->assertEquals(0, $result[1]['quantityAvailable']);
        $this->assertEquals(5, $result[1]['quantityOnOrder']);
        $this->assertEquals(7, $result[1]['quantityBackordered']);
    }

    public function testGetBackorderedItemsReturnsEmptyArrayWhenNoBackorderedItems(): void
    {
        // Arrange
        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        // Act
        $result = $this->command->getBackorderedItems();

        // Assert
        $this->assertEmpty($result);
    }

    public function testGenerateCsvDataWithItems(): void
    {
        // Arrange
        $items = [
            [
                'itemNumber' => 'ITEM-001',
                'name' => 'Test Item 1',
                'quantityAvailable' => 5,
                'quantityOnOrder' => 10,
                'quantityBackordered' => 3,
            ],
            [
                'itemNumber' => 'ITEM-002',
                'name' => 'Test Item 2',
                'quantityAvailable' => 0,
                'quantityOnOrder' => 5,
                'quantityBackordered' => 7,
            ],
        ];

        // Act
        $csv = $this->command->generateCsvData($items);

        // Assert
        $lines = explode("\n", trim($csv));
        $this->assertCount(3, $lines);

        // Verify header
        $this->assertEquals('"Item Number",Name,"Quantity Available","Quantity On Order","Quantity Backordered"', $lines[0]);

        // Verify data rows
        $this->assertEquals('ITEM-001,"Test Item 1",5,10,3', $lines[1]);
        $this->assertEquals('ITEM-002,"Test Item 2",0,5,7', $lines[2]);
    }

    public function testGenerateCsvDataWithEmptyItems(): void
    {
        // Act
        $csv = $this->command->generateCsvData([]);

        // Assert
        $lines = explode("\n", trim($csv));
        $this->assertCount(1, $lines);
        $this->assertEquals('"Item Number",Name,"Quantity Available","Quantity On Order","Quantity Backordered"', $lines[0]);
    }

    public function testCommandExecutesSuccessfullyWithNoBackorderedItems(): void
    {
        // Arrange
        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')->willReturn($repository);

        $application = new Application();
        $application->add($this->command);

        $commandTester = new CommandTester($this->command);

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('No backordered items found', $commandTester->getDisplay());
    }

    public function testCommandExecutesSuccessfullyWithBackorderedItems(): void
    {
        // Arrange
        $item = new Item();
        $item->itemId = 'ITEM-001';
        $item->itemName = 'Test Item';
        $item->quantityAvailable = 5;
        $item->quantityOnOrder = 10;
        $item->quantityBackOrdered = 3;

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$item]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')->willReturn($repository);

        $application = new Application();
        $application->add($this->command);

        $commandTester = new CommandTester($this->command);

        // Act
        $commandTester->execute([]);

        // Assert
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('ITEM-001', $commandTester->getDisplay());
        $this->assertStringContainsString('Test Item', $commandTester->getDisplay());
    }

    public function testCommandWritesToFileWhenOutputOptionProvided(): void
    {
        // Arrange
        $item = new Item();
        $item->itemId = 'ITEM-001';
        $item->itemName = 'Test Item';
        $item->quantityAvailable = 5;
        $item->quantityOnOrder = 10;
        $item->quantityBackOrdered = 3;

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$item]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->entityManager->method('getRepository')->willReturn($repository);

        $application = new Application();
        $application->add($this->command);

        $commandTester = new CommandTester($this->command);
        $outputFile = sys_get_temp_dir() . '/backordered-items-test.csv';

        // Act
        $commandTester->execute(['--output' => $outputFile]);

        // Assert
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileExists($outputFile);
        $this->assertStringContainsString("Report exported to: {$outputFile}", $commandTester->getDisplay());

        // Verify file contents
        $contents = file_get_contents($outputFile);
        $this->assertIsString($contents);
        $this->assertStringContainsString('ITEM-001', $contents);
        $this->assertStringContainsString('Test Item', $contents);

        // Cleanup
        unlink($outputFile);
    }
}

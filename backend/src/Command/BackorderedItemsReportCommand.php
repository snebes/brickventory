<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:report:backordered-items',
    description: 'Generate a report of backordered items (items on sales orders greater than quantity available)',
)]
class BackorderedItemsReportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path for CSV export'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputFile = $input->getOption('output');

        $backorderedItems = $this->getBackorderedItems();

        if (empty($backorderedItems)) {
            $io->success('No backordered items found.');
            return Command::SUCCESS;
        }

        $csvData = $this->generateCsvData($backorderedItems);

        if ($outputFile !== null) {
            $result = file_put_contents($outputFile, $csvData);
            if ($result === false) {
                $io->error("Failed to write to file: {$outputFile}");
                return Command::FAILURE;
            }
            $io->success("Report exported to: {$outputFile}");
        } else {
            $output->writeln($csvData);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array{itemNumber: string, name: string, quantityAvailable: int, quantityOnOrder: int, quantityBackordered: int}>
     */
    public function getBackorderedItems(): array
    {
        $repository = $this->entityManager->getRepository(Item::class);

        $items = $repository->createQueryBuilder('i')
            ->where('i.quantityBackOrdered > 0')
            ->orderBy('i.itemId', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'itemNumber' => $item->itemId,
                'name' => $item->itemName,
                'quantityAvailable' => $item->quantityAvailable,
                'quantityOnOrder' => $item->quantityOnOrder,
                'quantityBackordered' => $item->quantityBackOrdered,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array{itemNumber: string, name: string, quantityAvailable: int, quantityOnOrder: int, quantityBackordered: int}> $items
     */
    public function generateCsvData(array $items): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        // Write header row
        fputcsv($handle, ['Item Number', 'Name', 'Quantity Available', 'Quantity On Order', 'Quantity Backordered']);

        // Write data rows
        foreach ($items as $item) {
            fputcsv($handle, [
                $item['itemNumber'],
                $item['name'],
                $item['quantityAvailable'],
                $item['quantityOnOrder'],
                $item['quantityBackordered'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}

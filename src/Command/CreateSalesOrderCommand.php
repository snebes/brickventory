<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Item;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\SalesOrderCreatedEvent;
use App\Repository\SalesOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:sales-order:create',
    description: 'Create a new sales order with line items',
)]
class CreateSalesOrderCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesOrderRepository $salesOrderRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        // Ask for sales order reference/customer info
        $io->section('Create Sales Order');
        
        $notesQuestion = new Question('Enter customer notes (optional): ');
        $notes = $helper->ask($input, $output, $notesQuestion);

        // Create the sales order with auto-generated order number
        $salesOrder = new SalesOrder();
        $salesOrder->orderNumber = $this->salesOrderRepository->getNextOrderNumber();
        $salesOrder->orderDate = new \DateTime();
        $salesOrder->status = SalesOrder::STATUS_PENDING_FULFILLMENT;
        $salesOrder->notes = $notes;

        $io->writeln('');
        $io->writeln('Enter line items in format: <info>itemId/SKU quantity</info>');
        $io->writeln('(itemId/SKU can be either the item ID or a SKU from elementIds)');
        $io->writeln('Press Enter on a blank line to finish');
        $io->writeln('');

        $lineNumber = 1;
        while (true) {
            $lineQuestion = new Question("Line {$lineNumber}: ");
            $lineInput = $helper->ask($input, $output, $lineQuestion);

            // Empty line means we're done
            // Handle null or empty string from blank input
            if ($lineInput === null || trim($lineInput) === '') {
                break;
            }

            // Parse the line input
            $parts = preg_split('/\s+/', trim($lineInput));
            
            if (count($parts) !== 2) {
                $io->error('Invalid format. Expected: itemId/SKU quantity');
                continue;
            }

            [$itemIdentifier, $quantity] = $parts;

            // Validate quantity is a positive integer
            if (!is_numeric($quantity) || (int)$quantity <= 0) {
                $io->error('Quantity must be a positive integer');
                continue;
            }

            // Find the item by itemId or elementIds
            $item = $this->findItemByIdentifier($itemIdentifier);
            
            if (!$item) {
                $io->error("Item with identifier '{$itemIdentifier}' not found");
                continue;
            }

            // Check if sufficient quantity is available
            $quantityInt = (int)$quantity;
            if ($item->quantityAvailable < $quantityInt) {
                $io->warning("Only {$item->quantityAvailable} units available for {$item->itemName}. Adding to order anyway (will be backordered).");
            }

            // Create sales order line
            $line = new SalesOrderLine();
            $line->salesOrder = $salesOrder;
            $line->item = $item;
            $line->quantityOrdered = $quantityInt;

            $salesOrder->lines->add($line);
            
            $io->success("Added: {$item->itemName} (Qty: {$quantity}, Available: {$item->quantityAvailable})");
            $lineNumber++;
        }

        if ($salesOrder->lines->isEmpty()) {
            $io->error('At least one line item is required');
            return Command::FAILURE;
        }

        // Persist the sales order
        $this->entityManager->persist($salesOrder);
        $this->entityManager->flush();

        // Dispatch event for CQRS pattern
        $event = new SalesOrderCreatedEvent($salesOrder);
        $this->eventDispatcher->dispatch($event);

        $io->success([
            'Sales Order created successfully!',
            "Order Number: {$salesOrder->orderNumber}",
            "Total Lines: " . $salesOrder->lines->count()
        ]);

        return Command::SUCCESS;
    }

    /**
     * Find an item by database ID, itemId, or a SKU in the elementIds field
     */
    private function findItemByIdentifier(string $identifier): ?Item
    {
        // First, try to find by database ID if the identifier is numeric (backward compatibility)
        if (is_numeric($identifier)) {
            $item = $this->entityManager->getRepository(Item::class)->findOneBy(['id' => (int)$identifier]);
            if ($item) {
                return $item;
            }
        }

        // Next, try to find by itemId
        $item = $this->entityManager->getRepository(Item::class)->findOneBy(['itemId' => $identifier]);
        
        if ($item) {
            return $item;
        }

        // If not found, search in elementIds (comma-separated field)
        // Use a custom query to find items where elementIds contains the identifier
        $qb = $this->entityManager->getRepository(Item::class)->createQueryBuilder('i');
        $items = $qb
            ->where($qb->expr()->like('i.elementIds', ':identifier'))
            ->setParameter('identifier', '%' . $identifier . '%')
            ->getQuery()
            ->getResult();

        // Filter results to ensure exact match in comma-separated list
        foreach ($items as $item) {
            $elementIds = array_map('trim', explode(',', $item->elementIds));
            if (in_array($identifier, $elementIds, true)) {
                return $item;
            }
        }

        return null;
    }
}

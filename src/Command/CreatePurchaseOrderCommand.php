<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:purchase-order:create',
    description: 'Create a new purchase order with line items',
)]
class CreatePurchaseOrderCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        // Ask for purchase order reference
        $io->section('Create Purchase Order');
        
        $referenceQuestion = new Question('Enter purchase order reference: ');
        $reference = $helper->ask($input, $output, $referenceQuestion);
        
        if (empty($reference)) {
            $io->error('Purchase order reference is required.');
            return Command::FAILURE;
        }

        // Generate order number (using timestamp for uniqueness)
        $orderNumber = 'PO-' . date('YmdHis');

        // Create the purchase order
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = $orderNumber;
        $purchaseOrder->reference = $reference;
        $purchaseOrder->orderDate = new \DateTime();
        $purchaseOrder->status = 'pending';

        $io->writeln('');
        $io->writeln('Enter line items in format: <info>id quantity rate</info>');
        $io->writeln('Press Enter on a blank line to finish');
        $io->writeln('');

        $lineNumber = 1;
        while (true) {
            $lineQuestion = new Question("Line {$lineNumber}: ");
            $lineInput = $helper->ask($input, $output, $lineQuestion);

            // Empty line means we're done
            if (empty(trim($lineInput))) {
                break;
            }

            // Parse the line input
            $parts = preg_split('/\s+/', trim($lineInput));
            
            if (count($parts) !== 3) {
                $io->error('Invalid format. Expected: id quantity rate');
                continue;
            }

            [$itemId, $quantity, $rate] = $parts;

            // Validate quantity is a positive integer
            if (!is_numeric($quantity) || (int)$quantity <= 0) {
                $io->error('Quantity must be a positive integer');
                continue;
            }

            // Validate rate is a positive number
            if (!is_numeric($rate) || (float)$rate < 0) {
                $io->error('Rate must be a positive number');
                continue;
            }

            // Find the item
            $item = $this->entityManager->getRepository(Item::class)->findOneBy(['id' => (int)$itemId]);
            
            if (!$item) {
                $io->error("Item with ID {$itemId} not found");
                continue;
            }

            // Create purchase order line
            $line = new PurchaseOrderLine();
            $line->purchaseOrder = $purchaseOrder;
            $line->item = $item;
            $line->quantityOrdered = (int)$quantity;
            $line->rate = (float)$rate;

            $purchaseOrder->lines->add($line);
            
            $io->success("Added: {$item->itemName} (Qty: {$quantity}, Rate: {$rate})");
            $lineNumber++;
        }

        if ($purchaseOrder->lines->isEmpty()) {
            $io->error('At least one line item is required');
            return Command::FAILURE;
        }

        // Persist the purchase order
        $this->entityManager->persist($purchaseOrder);
        $this->entityManager->flush();

        // Dispatch event for CQRS pattern
        $event = new PurchaseOrderCreatedEvent($purchaseOrder);
        $this->eventDispatcher->dispatch($event);

        $io->success([
            'Purchase Order created successfully!',
            "Order Number: {$orderNumber}",
            "Reference: {$reference}",
            "Total Lines: " . $purchaseOrder->lines->count()
        ]);

        return Command::SUCCESS;
    }
}

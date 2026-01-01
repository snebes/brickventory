<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ItemReceipt;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\ItemReceivedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:item:receive',
    description: 'Receive items from a purchase order',
)]
class ReceiveItemCommand extends Command
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

        $io->section('Receive Items from Purchase Order');

        // Ask for purchase order ID or reference
        $poQuestion = new Question('Enter Purchase Order ID or Reference: ');
        $purchaseOrderIdentifier = $helper->ask($input, $output, $poQuestion);

        if (empty($purchaseOrderIdentifier)) {
            $io->error('Purchase Order ID or Reference is required');
            return Command::FAILURE;
        }

        // Find the purchase order by ID or reference
        $purchaseOrder = $this->findPurchaseOrderByIdentifier($purchaseOrderIdentifier);

        if (!$purchaseOrder) {
            $io->error("Purchase Order with identifier '{$purchaseOrderIdentifier}' not found");
            return Command::FAILURE;
        }

        $io->writeln("Purchase Order: {$purchaseOrder->orderNumber}");
        $io->writeln("Reference: {$purchaseOrder->reference}");
        $io->writeln('');

        // Display line items
        $io->writeln('Line Items:');
        foreach ($purchaseOrder->lines as $index => $line) {
            $io->writeln(sprintf(
                '%d. %s - Ordered: %d, Received: %d',
                $index + 1,
                $line->item->itemName,
                $line->quantityOrdered,
                $line->quantityReceived
            ));
        }
        $io->writeln('');

        // Ask for quantities to receive
        $io->writeln('Enter quantities to receive for each line (or press Enter to skip):');
        $receiptDate = new \DateTime();
        $hasReceipts = false;

        foreach ($purchaseOrder->lines as $index => $line) {
            $remaining = $line->quantityOrdered - $line->quantityReceived;
            
            if ($remaining <= 0) {
                $io->writeln(sprintf('Line %d: Already fully received', $index + 1));
                continue;
            }

            $quantityQuestion = new Question(
                sprintf('Line %d (%s) - Remaining: %d, Receive: ',
                    $index + 1,
                    $line->item->itemName,
                    $remaining
                )
            );
            
            $quantityInput = $helper->ask($input, $output, $quantityQuestion);

            if (empty($quantityInput)) {
                continue;
            }

            $quantity = (int)$quantityInput;

            if ($quantity <= 0 || $quantity > $remaining) {
                $io->error("Invalid quantity. Must be between 1 and {$remaining}");
                continue;
            }

            // Update line quantity received
            $line->quantityReceived += $quantity;
            $this->entityManager->persist($line);

            // Dispatch ItemReceivedEvent (event sourcing)
            $event = new ItemReceivedEvent($line->item, $quantity, $purchaseOrder);
            $this->eventDispatcher->dispatch($event);

            $hasReceipts = true;
            $io->success("Received {$quantity} of {$line->item->itemName}");
        }

        if (!$hasReceipts) {
            $io->warning('No items received');
            return Command::SUCCESS;
        }

        // Create ItemReceipt record
        $itemReceipt = new ItemReceipt();
        $itemReceipt->purchaseOrder = $purchaseOrder;
        $itemReceipt->receiptDate = $receiptDate;
        $itemReceipt->status = 'received';
        
        $this->entityManager->persist($itemReceipt);

        // Update purchase order status if fully received
        $allReceived = true;
        foreach ($purchaseOrder->lines as $line) {
            if ($line->quantityReceived < $line->quantityOrdered) {
                $allReceived = false;
                break;
            }
        }

        if ($allReceived) {
            $purchaseOrder->status = 'received';
            $this->entityManager->persist($purchaseOrder);
        }

        $this->entityManager->flush();

        $io->success('Items received successfully!');
        
        return Command::SUCCESS;
    }

    /**
     * Find a purchase order by ID or reference number
     */
    private function findPurchaseOrderByIdentifier(string $identifier): ?PurchaseOrder
    {
        // Try to find by ID if the identifier is numeric
        if (is_numeric($identifier)) {
            $purchaseOrder = $this->entityManager->getRepository(PurchaseOrder::class)
                ->findOneBy(['id' => (int)$identifier]);
            
            if ($purchaseOrder) {
                return $purchaseOrder;
            }
        }

        // Try to find by reference number
        return $this->entityManager->getRepository(PurchaseOrder::class)
            ->findOneBy(['reference' => $identifier]);
    }
}

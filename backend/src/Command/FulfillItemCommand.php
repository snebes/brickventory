<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ItemFulfillment;
use App\Entity\SalesOrder;
use App\Event\ItemFulfilledEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:item:fulfill',
    description: 'Fulfill items for a sales order',
)]
class FulfillItemCommand extends Command
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

        $io->section('Fulfill Items for Sales Order');

        // Ask for sales order ID
        $soQuestion = new Question('Enter Sales Order ID: ');
        $salesOrderId = $helper->ask($input, $output, $soQuestion);

        if (empty($salesOrderId) || !is_numeric($salesOrderId)) {
            $io->error('Invalid Sales Order ID');
            return Command::FAILURE;
        }

        // Find the sales order
        $salesOrder = $this->entityManager->getRepository(SalesOrder::class)
            ->findOneBy(['id' => (int)$salesOrderId]);

        if (!$salesOrder) {
            $io->error("Sales Order with ID {$salesOrderId} not found");
            return Command::FAILURE;
        }

        $io->writeln("Sales Order: {$salesOrder->orderNumber}");
        $io->writeln('');

        // Display line items
        $io->writeln('Line Items:');
        foreach ($salesOrder->lines as $index => $line) {
            $io->writeln(sprintf(
                '%d. %s - Ordered: %d, Fulfilled: %d, Available: %d',
                $index + 1,
                $line->item->itemName,
                $line->quantityOrdered,
                $line->quantityFulfilled,
                $line->item->quantityOnHand
            ));
        }
        $io->writeln('');

        // Ask for quantities to fulfill
        $io->writeln('Enter quantities to fulfill for each line (or press Enter to skip):');
        $fulfillmentDate = new \DateTime();
        $hasFulfillments = false;

        foreach ($salesOrder->lines as $index => $line) {
            $remaining = $line->quantityOrdered - $line->quantityFulfilled;

            if ($remaining <= 0) {
                $io->writeln(sprintf('Line %d: Already fully fulfilled', $index + 1));
                continue;
            }

            $maxFulfillable = min($remaining, $line->item->quantityOnHand);

            if ($maxFulfillable <= 0) {
                $io->warning(sprintf(
                    'Line %d (%s): No inventory available (on hand: %d)',
                    $index + 1,
                    $line->item->itemName,
                    $line->item->quantityOnHand
                ));
                continue;
            }

            $quantityQuestion = new Question(
                sprintf('Line %d (%s) - Remaining: %d, Max Available: %d, Fulfill: ',
                    $index + 1,
                    $line->item->itemName,
                    $remaining,
                    $maxFulfillable
                )
            );

            $quantityInput = $helper->ask($input, $output, $quantityQuestion);

            if (empty($quantityInput)) {
                continue;
            }

            $quantity = (int)$quantityInput;

            if ($quantity <= 0 || $quantity > $maxFulfillable) {
                $io->error("Invalid quantity. Must be between 1 and {$maxFulfillable}");
                continue;
            }

            // Update line quantity fulfilled
            $line->quantityFulfilled += $quantity;
            $this->entityManager->persist($line);

            // Dispatch ItemFulfilledEvent (event sourcing)
            $event = new ItemFulfilledEvent($line->item, $quantity, $salesOrder);
            $this->eventDispatcher->dispatch($event);

            $hasFulfillments = true;
            $io->success("Fulfilled {$quantity} of {$line->item->itemName}");
        }

        if (!$hasFulfillments) {
            $io->warning('No items fulfilled');
            return Command::SUCCESS;
        }

        // Create ItemFulfillment record
        $itemFulfillment = new ItemFulfillment();
        $itemFulfillment->salesOrder = $salesOrder;
        $itemFulfillment->setFulfillmentDate($fulfillmentDate);
        $itemFulfillment->status = ItemFulfillment::STATUS_SHIPPED;

        $this->entityManager->persist($itemFulfillment);

        // Update sales order status if fully fulfilled
        $allFulfilled = true;
        foreach ($salesOrder->lines as $line) {
            if ($line->quantityFulfilled < $line->quantityOrdered) {
                $allFulfilled = false;
                break;
            }
        }

        if ($allFulfilled) {
            $salesOrder->status = SalesOrder::STATUS_FULFILLED;
            $this->entityManager->persist($salesOrder);
        }

        $this->entityManager->flush();

        $io->success('Items fulfilled successfully!');

        return Command::SUCCESS;
    }
}

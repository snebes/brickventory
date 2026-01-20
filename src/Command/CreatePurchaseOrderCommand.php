<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Entity\Vendor;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
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

        // Ask for vendor (required per NetSuite ERP model)
        $vendors = $this->entityManager->getRepository(Vendor::class)
            ->findBy(['active' => true], ['vendorName' => 'ASC']);

        if (empty($vendors)) {
            $io->error('No active vendors found. Please create a vendor first.');
            return Command::FAILURE;
        }

        $vendorChoices = [];
        foreach ($vendors as $vendor) {
            $vendorChoices[$vendor->vendorCode] = "{$vendor->vendorCode} - {$vendor->vendorName}";
        }

        $vendorQuestion = new ChoiceQuestion(
            'Select vendor (required): ',
            array_values($vendorChoices)
        );
        $vendorQuestion->setErrorMessage('Vendor %s is invalid.');

        $selectedVendorDisplay = $helper->ask($input, $output, $vendorQuestion);
        
        // Find the selected vendor
        $selectedVendorCode = array_search($selectedVendorDisplay, $vendorChoices);
        $selectedVendor = $this->entityManager->getRepository(Vendor::class)
            ->findOneBy(['vendorCode' => $selectedVendorCode]);

        if (!$selectedVendor) {
            $io->error('Invalid vendor selected.');
            return Command::FAILURE;
        }

        $io->success("Vendor selected: {$selectedVendor->vendorName}");
        
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
        $purchaseOrder->vendor = $selectedVendor;
        $purchaseOrder->orderNumber = $orderNumber;
        $purchaseOrder->reference = $reference;
        $purchaseOrder->orderDate = new \DateTime();
        $purchaseOrder->status = 'Pending Approval';

        // Auto-populate vendor defaults
        $purchaseOrder->paymentTerms = $selectedVendor->defaultPaymentTerms;
        $purchaseOrder->currency = $selectedVendor->defaultCurrency;
        $purchaseOrder->billToAddress = $selectedVendor->billingAddress;

        $io->writeln('');
        $io->writeln('Enter line items in format: <info>itemId/SKU quantity rate</info>');
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
            
            if (count($parts) !== 3) {
                $io->error('Invalid format. Expected: itemId/SKU quantity rate');
                continue;
            }

            [$itemIdentifier, $quantity, $rate] = $parts;

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

            // Find the item by itemId or elementIds
            $item = $this->findItemByIdentifier($itemIdentifier);
            
            if (!$item) {
                $io->error("Item with identifier '{$itemIdentifier}' not found");
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

        // Calculate totals
        $purchaseOrder->calculateTotals();

        // Persist the purchase order
        $this->entityManager->persist($purchaseOrder);
        $this->entityManager->flush();

        // Dispatch event for CQRS pattern
        $event = new PurchaseOrderCreatedEvent($purchaseOrder);
        $this->eventDispatcher->dispatch($event);

        $io->success([
            'Purchase Order created successfully!',
            "Order Number: {$orderNumber}",
            "Vendor: {$selectedVendor->vendorName}",
            "Reference: {$reference}",
            "Total Lines: " . $purchaseOrder->lines->count()
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

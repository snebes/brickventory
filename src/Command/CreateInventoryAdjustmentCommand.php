<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\InventoryAdjustment;
use App\Entity\InventoryAdjustmentLine;
use App\Entity\Item;
use App\Event\InventoryAdjustedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:inventory:adjust',
    description: 'Create an inventory adjustment to manually add or remove inventory',
)]
class CreateInventoryAdjustmentCommand extends Command
{
    private const REASONS = [
        'physical_count' => 'Physical Count',
        'damaged' => 'Damaged Goods',
        'lost' => 'Lost/Missing',
        'found' => 'Found/Recovered',
        'correction' => 'Correction',
        'transfer_in' => 'Transfer In',
        'transfer_out' => 'Transfer Out',
        'production' => 'Production Output',
        'scrap' => 'Scrap/Waste',
        'sample' => 'Sample/Demo',
        'other' => 'Other',
    ];

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

        $io->title('Create Inventory Adjustment');

        // Ask for reason
        $reasonQuestion = new ChoiceQuestion(
            'Select adjustment reason:',
            array_values(self::REASONS),
            0
        );
        $reasonName = $helper->ask($input, $output, $reasonQuestion);
        $reason = array_search($reasonName, self::REASONS);

        // Ask for memo
        $memoQuestion = new Question('Enter memo (optional): ');
        $memo = $helper->ask($input, $output, $memoQuestion);

        // Create adjustment record
        $adjustment = new InventoryAdjustment();
        $adjustment->adjustmentNumber = 'ADJ-' . date('YmdHis');
        $adjustment->reason = $reason;
        $adjustment->memo = $memo ?: null;
        $adjustment->status = 'approved';

        $this->entityManager->persist($adjustment);
        $this->entityManager->flush(); // Flush to get the ID

        $io->writeln('');
        $io->writeln("Adjustment Number: {$adjustment->adjustmentNumber}");
        $io->writeln("Reason: {$reasonName}");
        $io->writeln('');

        // Add line items
        $io->section('Add Items to Adjustment');
        $hasLines = false;

        while (true) {
            // Ask for item
            $itemQuestion = new Question('Enter Item ID or Item Name (or press Enter to finish): ');
            $itemIdentifier = $helper->ask($input, $output, $itemQuestion);

            if (empty($itemIdentifier)) {
                break;
            }

            $item = $this->findItemByIdentifier($itemIdentifier);

            if (!$item) {
                $io->error("Item '{$itemIdentifier}' not found");
                continue;
            }

            $io->writeln(sprintf(
                'Found: %s - %s (Current On Hand: %d)',
                $item->itemId,
                $item->itemName,
                $item->quantityOnHand
            ));

            // Ask for quantity change
            $quantityQuestion = new Question('Enter quantity change (positive to add, negative to subtract): ');
            $quantityInput = $helper->ask($input, $output, $quantityQuestion);

            if (!is_numeric($quantityInput)) {
                $io->error('Quantity must be a number');
                continue;
            }

            $quantityChange = (int)$quantityInput;

            if ($quantityChange === 0) {
                $io->warning('Skipping zero quantity');
                continue;
            }

            // Ask for notes
            $notesQuestion = new Question('Enter notes for this line (optional): ');
            $notes = $helper->ask($input, $output, $notesQuestion);

            // Create adjustment line
            $adjustmentLine = new InventoryAdjustmentLine();
            $adjustmentLine->inventoryAdjustment = $adjustment;
            $adjustmentLine->item = $item;
            $adjustmentLine->quantityChange = $quantityChange;
            $adjustmentLine->notes = $notes ?: null;

            $adjustment->lines->add($adjustmentLine);
            $this->entityManager->persist($adjustmentLine);

            // Dispatch event for inventory update
            $event = new InventoryAdjustedEvent($item, $quantityChange, $adjustment);
            $this->eventDispatcher->dispatch($event);

            $hasLines = true;
            $changeType = $quantityChange > 0 ? 'Added' : 'Removed';
            $io->success("{$changeType} {$quantityChange} of {$item->itemName} (New On Hand: {$item->quantityOnHand})");
            $io->writeln('');
        }

        if (!$hasLines) {
            $io->warning('No items added to adjustment. Removing empty adjustment.');
            $this->entityManager->remove($adjustment);
            $this->entityManager->flush();
            return Command::SUCCESS;
        }

        // Confirm and save
        $confirmQuestion = new ConfirmationQuestion('Save this inventory adjustment? [Y/n] ', true);
        if (!$helper->ask($input, $output, $confirmQuestion)) {
            $io->warning('Adjustment cancelled.');
            return Command::SUCCESS;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Inventory adjustment %s created with %d line(s). Inventory has been updated immediately.',
            $adjustment->adjustmentNumber,
            $adjustment->lines->count()
        ));

        return Command::SUCCESS;
    }

    /**
     * Find an item by ID, itemId, or element ID
     */
    private function findItemByIdentifier(string $identifier): ?Item
    {
        // Try to find by database ID if numeric
        if (is_numeric($identifier)) {
            $item = $this->entityManager->getRepository(Item::class)
                ->findOneBy(['id' => (int)$identifier]);
            
            if ($item) {
                return $item;
            }
        }

        // Try to find by itemId
        $item = $this->entityManager->getRepository(Item::class)
            ->findOneBy(['itemId' => $identifier]);

        if ($item) {
            return $item;
        }

        // Try to find by elementIds (contains search)
        $items = $this->entityManager->getRepository(Item::class)
            ->createQueryBuilder('i')
            ->where('i.elementIds LIKE :identifier')
            ->setParameter('identifier', '%' . $identifier . '%')
            ->getQuery()
            ->getResult();

        return $items[0] ?? null;
    }
}

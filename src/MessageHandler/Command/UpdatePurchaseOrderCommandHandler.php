<?php

declare(strict_types=1);

namespace App\MessageHandler\Command;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Message\Command\UpdatePurchaseOrderCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdatePurchaseOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(UpdatePurchaseOrderCommand $command): void
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($command->id);
        
        if (!$po) {
            throw new \InvalidArgumentException("Purchase order {$command->id} not found");
        }

        // Update basic fields
        $po->orderDate = new \DateTime($command->orderDate);
        $po->status = $command->status;
        $po->reference = $command->reference;
        $po->notes = $command->notes;

        // Remove existing lines
        foreach ($po->lines as $line) {
            $this->entityManager->remove($line);
        }
        $po->lines->clear();

        // Add new lines
        foreach ($command->lines as $lineData) {
            $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
            }
            
            $line = new PurchaseOrderLine();
            $line->purchaseOrder = $po;
            $line->item = $item;
            $line->quantityOrdered = $lineData['quantityOrdered'];
            $line->rate = $lineData['rate'];
            
            $po->lines->add($line);
        }

        $this->entityManager->flush();
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\Command;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use App\Message\Command\CreatePurchaseOrderCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreatePurchaseOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(CreatePurchaseOrderCommand $command): int
    {
        $po = new PurchaseOrder();
        $po->orderNumber = $command->orderNumber ?? 'PO-' . date('YmdHis');
        $po->orderDate = new \DateTime($command->orderDate);
        $po->status = $command->status;
        $po->reference = $command->reference;
        $po->notes = $command->notes;

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

        $this->entityManager->persist($po);
        $this->entityManager->flush();

        // Dispatch event to update inventory
        $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($po));

        return $po->id;
    }
}

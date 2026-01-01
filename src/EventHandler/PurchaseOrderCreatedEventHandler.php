<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PurchaseOrderCreatedEvent::class)]
class PurchaseOrderCreatedEventHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(PurchaseOrderCreatedEvent $event): void
    {
        $purchaseOrder = $event->getPurchaseOrder();

        // Update inventory for each line item
        foreach ($purchaseOrder->lines as $line) {
            $item = $line->item;
            
            // Update quantityOnOrder when a purchase order is created
            $item->quantityOnOrder += $line->quantityOrdered;
            
            // Update quantityAvailable (this is the quantity that can be sold)
            // quantityAvailable = quantityOnHand + quantityOnOrder - quantityBackOrdered
            $item->quantityAvailable = $item->quantityOnHand + $item->quantityOnOrder - $item->quantityBackOrdered;
            
            $this->entityManager->persist($item);
        }

        $this->entityManager->flush();
    }
}

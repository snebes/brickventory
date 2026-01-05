<?php

declare(strict_types=1);

namespace App\MessageHandler\Command;

use App\Entity\Item;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Message\Command\UpdateSalesOrderCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateSalesOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(UpdateSalesOrderCommand $command): void
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($command->id);
        
        if (!$so) {
            throw new \InvalidArgumentException("Sales order {$command->id} not found");
        }

        // Update basic fields
        $so->orderDate = new \DateTime($command->orderDate);
        $so->status = $command->status;
        $so->notes = $command->notes;

        // Remove existing lines
        foreach ($so->lines as $line) {
            $this->entityManager->remove($line);
        }
        $so->lines->clear();

        // Add new lines
        foreach ($command->lines as $lineData) {
            $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
            }
            
            $line = new SalesOrderLine();
            $line->salesOrder = $so;
            $line->item = $item;
            $line->quantityOrdered = $lineData['quantityOrdered'];
            
            $so->lines->add($line);
        }

        $this->entityManager->flush();
    }
}

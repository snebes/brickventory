<?php

declare(strict_types=1);

namespace App\MessageHandler\Command;

use App\Entity\Item;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\SalesOrderCreatedEvent;
use App\Message\Command\CreateSalesOrderCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateSalesOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(CreateSalesOrderCommand $command): int
    {
        $so = new SalesOrder();
        $so->orderNumber = $command->orderNumber ?? 'SO-' . date('YmdHis');
        $so->orderDate = new \DateTime($command->orderDate);
        $so->status = $command->status;
        $so->notes = $command->notes;

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

        $this->entityManager->persist($so);
        $this->entityManager->flush();

        // Dispatch event to update inventory
        $this->eventDispatcher->dispatch(new SalesOrderCreatedEvent($so));

        return $so->id;
    }
}

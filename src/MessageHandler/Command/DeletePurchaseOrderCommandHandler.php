<?php

declare(strict_types=1);

namespace App\MessageHandler\Command;

use App\Entity\PurchaseOrder;
use App\Message\Command\DeletePurchaseOrderCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeletePurchaseOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(DeletePurchaseOrderCommand $command): void
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($command->id);
        
        if (!$po) {
            throw new \InvalidArgumentException("Purchase order {$command->id} not found");
        }

        $this->entityManager->remove($po);
        $this->entityManager->flush();
    }
}

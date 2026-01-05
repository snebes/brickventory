<?php

declare(strict_types=1);

namespace App\MessageHandler\Command;

use App\Entity\SalesOrder;
use App\Message\Command\DeleteSalesOrderCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteSalesOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(DeleteSalesOrderCommand $command): void
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($command->id);
        
        if (!$so) {
            throw new \InvalidArgumentException("Sales order {$command->id} not found");
        }

        $this->entityManager->remove($so);
        $this->entityManager->flush();
    }
}

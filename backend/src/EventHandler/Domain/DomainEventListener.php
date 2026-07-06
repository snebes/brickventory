<?php

declare(strict_types=1);

namespace App\EventHandler\Domain;

use App\Entity\Accounting\Item;
use App\Entity\Domain\DomainEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class DomainEventListener
{
    /** @var DomainEvent[] */
    private array $collectedEvents = [];

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
        );

        foreach ($entities as $entity) {
            if (!method_exists($entity, 'popPendingEvents')) {
                continue;
            }

            foreach ($entity->popPendingEvents() as $data) {
                $event = new DomainEvent();
                $event->aggregateId = $entity->id;
                $event->aggregateType = $this->resolveType($entity);
                $event->event = $data['type'];
                $event->payload = $data['payload'];
                $event->version = $data['version'];

                $em->persist($event);
                $uow->computeChangeSet($em->getClassMetadata(DomainEvent::class), $event);
                $this->collectedEvents[] = $event;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->collectedEvents = [];
    }

    private function resolveType(object $entity): string
    {
        return match (true) {
            $entity instanceof Item => 'accounting.item',
            default => new ReflectionClass($entity)->getShortName(),
        };
    }
}

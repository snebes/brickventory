<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\Accounting\Event\ItemCreated;
use App\Entity\Accounting\Event\ItemUpdated;
use App\Entity\Accounting\Item;
use App\Entity\BrickLink\BrickLinkColorCode;
use App\Entity\BrickLink\BrickLinkPart;
use App\Message\BrickLink\SyncBrickLinkItemsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function mb_substr;
use function mb_trim;
use function sprintf;

#[AsMessageHandler]
class SyncBrickLinkItemsMessageHandler extends AbstractBrickLinkMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(SyncBrickLinkItemsMessage $message): void
    {
        $this->addParts();
        $this->addMinifigs();
        $this->addSets();

        $this->em->flush();
    }

    protected function addParts(): void
    {
        $parts = $this->em->getRepository(BrickLinkColorCode::class)->findAll();
        $repo = $this->em->getRepository(Item::class);

        foreach ($parts as $part) {
            $description = mb_trim(sprintf('%s %s', $part->color->name, $part->part->name));
            $displayName = mb_substr($description, 0, 255);
            $item = $repo->findOneBy(['itemId' => $part->itemId]);

            if (!$item) {
                $item = new Item();
                $item->apply(new ItemCreated(
                    itemId: $part->itemId,
                    displayName: $displayName,
                    description: $description,
                ));
                $this->em->persist($item);
            } else {
                $item->apply(new ItemUpdated(
                    displayName: $displayName,
                    description: $description,
                ));
            }
        }
    }

    protected function addMinifigs(): void
    {
        $figs = $this->em->getRepository(BrickLinkPart::class)->findBy(['type' => 'M']);
        $repo = $this->em->getRepository(Item::class);

        foreach ($figs as $fig) {
            $id = sprintf('M-%s', $fig->part);
            $item = $repo->findOneBy(['itemId' => $id]);

            if (!$item) {
                $item = new Item();
                $item->apply(new ItemCreated(
                    itemId: $id,
                    displayName: $fig->name,
                    description: $fig->name,
                ));
            } else {
                $item->apply(new ItemUpdated(
                    displayName: $fig->name,
                    description: $fig->name,
                ));
            }
        }
    }

    protected function addSets(): void
    {
        $sets = $this->em->getRepository(BrickLinkPart::class)->findBy(['type' => 'S']);
        $repo = $this->em->getRepository(Item::class);

        foreach ($sets as $set) {
            $id = sprintf('S-%s', $set->part);
            $item = $repo->findOneBy(['itemId' => $id]);

            if (!$item) {
                $item = new Item();
                $item->apply(new ItemCreated(
                    itemId: $id,
                    displayName: $set->name,
                    description: $set->name,
                ));
            } else {
                $item->apply(new ItemUpdated(
                    displayName: $set->name,
                    description: $set->name,
                ));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\Accounting\Item;
use App\Entity\BrickLink\BrickLinkColorCode;
use App\Message\BrickLink\SyncBrickLinkItemsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function mb_trim;
use function sprintf;

#[AsMessageHandler]
class SyncBrickLinkItemsMessageHandler extends AbstractBrickLinkMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(SyncBrickLinkItemsMessage $message): void
    {
        $this->addParts();
    }

    protected function addParts(): void
    {
        $parts = $this->em->getRepository(BrickLinkColorCode::class)->findAll();
        $repo = $this->em->getRepository(Item::class);

        foreach ($parts as $part) {
            $item = $repo->findOneBy(['itemId' => $part->itemId]);

            if (!$item) {
                $item = new Item();
                $item->itemId = $part->itemId;
                $this->em->persist($item);
            }

            $item->description = mb_trim(sprintf('%s %s', $part->color->name, $part->part->name));
            $item->displayName = mb_substr($item->description, 0, 255);
            $item->isActive = true;
        }

        $this->em->flush();
    }
}

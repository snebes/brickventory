<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\BrickLink\BrickLinkCategory;
use App\Message\BrickLink\AddBrickLinkCategoriesMessage;
use Doctrine\ORM\EntityManagerInterface;
use DOM\XMLDocument;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/*
<ITEM>
    <CATEGORY>652</CATEGORY>
    <CATEGORYNAME>Bohrok-Kal</CATEGORYNAME>
</ITEM>
 */

#[AsMessageHandler]
class AddBrickLinkCategoriesMessageHandler extends AbstractBrickLinkMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(AddBrickLinkCategoriesMessage $message): void
    {
        $repo = $this->em->getRepository(BrickLinkCategory::class);
        $document = XMLDocument::createFromFile($message->xmlFile);
        $items = $document->getElementsByTagName('ITEM');

        foreach ($items as $category) {
            $id = (int) $this->getTextContent($category, 'CATEGORY');
            $entity = $repo->find($id);
            if (!$entity) {
                $entity = new BrickLinkCategory();
                $entity->id = $id;
                $this->em->persist($entity);
            }

            $entity->name = $this->getTextContent($category, 'CATEGORYNAME');
        }

        $this->em->flush();
    }
}

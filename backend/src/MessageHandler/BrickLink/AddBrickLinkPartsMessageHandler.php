<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\BrickLink\BrickLinkCategory;
use App\Entity\BrickLink\BrickLinkPart;
use App\Message\BrickLink\AddBrickLinkPartsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Dom\Element;
use DOM\XMLDocument;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function explode;

/*
<ITEM>
    <ITEMTYPE>P</ITEMTYPE>
    <ITEMID>1</ITEMID>
    <ITEMNAME>Homemaker Bookcase 2 x 4 x 4</ITEMNAME>
    <ALTITEMIDS></ALTITEMIDS>
    <CATEGORY>85</CATEGORY>
    <ITEMWEIGHT>5.8</ITEMWEIGHT>
    <ITEMDIMX>2</ITEMDIMX>
    <ITEMDIMY>4</ITEMDIMY>
    <ITEMDIMZ>4</ITEMDIMZ>
</ITEM>
 */

#[AsMessageHandler]
class AddBrickLinkPartsMessageHandler extends AbstractBrickLinkMessageHandler
{
    private array $categories = [];

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(AddBrickLinkPartsMessage $message): void
    {
        $repo = $this->em->getRepository(BrickLinkPart::class);
        $document = XMLDocument::createFromFile($message->xmlFile);
        $items = $document->getElementsByTagName('ITEM');

        foreach ($items as $item) {
            $part = $this->getTextContent($item, 'ITEMID');
            $entity = $repo->findOneBy(['part' => $part]);
            if (!$entity) {
                $entity = new BrickLinkPart();
                $entity->part = $part;
                $this->em->persist($entity);
            }

            $entity->type = $this->getTextContent($item, 'ITEMTYPE');
            $entity->name = $this->getTextContent($item, 'ITEMNAME');
            $entity->altIds = explode(',', $this->getTextContent($item, 'ALTITEMIDS'));
            $entity->category = $this->getCategory($item);
            $entity->weight = (float) $this->getTextContent($item, 'ITEMWEIGHT');
            $entity->dimensionX = (float) $this->getTextContent($item, 'ITEMDIMX');
            $entity->dimensionY = (float) $this->getTextContent($item, 'ITEMDIMY');
            $entity->dimensionZ = (float) $this->getTextContent($item, 'ITEMDIMZ');
        }

        $this->em->flush();
    }

    private function getCategory(Element $element): BrickLinkCategory
    {
        if (empty($this->categories)) {
            $categories = $this->em->getRepository(BrickLinkCategory::class)->findAll();
            foreach ($categories as $category) {
                $this->categories[$category->id] = $category;
            }
        }

        $id = (int) $this->getTextContent($element, 'CATEGORY');
        return $this->categories[$id];
    }
}

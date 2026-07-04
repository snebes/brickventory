<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\BrickLink\BrickLinkColor;
use App\Message\BrickLink\AddBrickLinkColorsMessage;
use Doctrine\ORM\EntityManagerInterface;
use DOM\XMLDocument;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/*
<ITEM>
    <COLOR>0</COLOR>
    <COLORNAME>(Not Applicable)</COLORNAME>
    <COLORRGB/>
    <COLORTYPE>N/A</COLORTYPE>
    <COLORCNTPARTS>6082</COLORCNTPARTS>
    <COLORCNTSETS>15318</COLORCNTSETS>
    <COLORCNTWANTED>92468</COLORCNTWANTED>
    <COLORCNTINV>16189</COLORCNTINV>
    <COLORYEARFROM>1954</COLORYEARFROM>
    <COLORYEARTO>2026</COLORYEARTO>
</ITEM>
 */

#[AsMessageHandler]
class AddBrickLinkColorsMessageHandler extends AbstractBrickLinkMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(AddBrickLinkColorsMessage $message): void
    {
        $repo = $this->em->getRepository(BrickLinkColor::class);
        $document = XMLDocument::createFromFile($message->xmlFile);
        $items = $document->getElementsByTagName('ITEM');

        foreach ($items as $color) {
            $id = (int) $this->getTextContent($color, 'COLOR');
            $entity = $repo->find($id);
            if (!$entity) {
                $entity = new BrickLinkColor();
                $entity->id = $id;
                $this->em->persist($entity);
            }

            $entity->name = $this->getTextContent($color, 'COLORNAME');
            $entity->rgb = $this->getTextContent($color, 'COLORRGB');
            $entity->type = $this->getTextContent($color, 'COLORTYPE');
            $entity->yearFrom = $this->getTextContent($color, 'COLORYEARFROM');
            $entity->yearTo = $this->getTextContent($color, 'COLORYEARTO');
        }

        $this->em->flush();
    }
}

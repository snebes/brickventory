<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\BrickLink\BrickLinkColor;
use App\Entity\BrickLink\BrickLinkColorCode;
use App\Message\BrickLink\AddBrickLinkColorCodesMessage;
use Doctrine\ORM\EntityManagerInterface;
use Dom\Element;
use DOM\XMLDocument;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function sprintf;

/*
<ITEM>
    <ITEMTYPE>P</ITEMTYPE>
    <ITEMID>44</ITEMID>
    <COLOR>Black</COLOR>
    <CODENAME>4221514</CODENAME>
</ITEM>
 */

#[AsMessageHandler]
class AddBrickLinkColorCodesMessageHandler extends AbstractBrickLinkMessageHandler
{
    private array $colors = [];

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(AddBrickLinkColorCodesMessage $message): void
    {
        $repo = $this->em->getRepository(BrickLinkColorCode::class);
        $document = XMLDocument::createFromFile($message->xmlFile);
        $items = $document->getElementsByTagName('ITEM');

        $matrix = [];
        foreach ($items as $code) {
            $part = sprintf('%s-%s', $this->getTextContent($code, 'ITEMID'), $this->getColor($code));
            if (!isset($matrix[$part])) {
                $matrix[$part] = [];
            }
            $matrix[$part][] = $this->getTextContent($code, 'CODENAME');
        }

        foreach ($matrix as $part => $elementIds) {
            $entity = $repo->findOneBy(['part' => $part]);
            if (!$entity) {
                $entity = new BrickLinkColorCode();
                $entity->part = $part;
                $this->em->persist($entity);
            }

            $entity->elementIds = $elementIds;
        }

        $this->em->flush();
    }

    public function getColor(Element $element): string
    {
        if (empty($this->colors)) {
            $colors = $this->em->getRepository(BrickLinkColor::class)->findAll();
            foreach ($colors as $color) {
                $this->colors[$color->name] = sprintf('%02d', $color->id);
            }
        }

        return $this->colors[$this->getTextContent($element, 'COLOR')];
    }
}

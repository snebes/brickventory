<?php

declare(strict_types=1);

namespace App\MessageHandler\BrickLink;

use App\Entity\BrickLink\BrickLinkColor;
use App\Entity\BrickLink\BrickLinkColorCode;
use App\Entity\BrickLink\BrickLinkPart;
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
    private array $parts = [];

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function __invoke(AddBrickLinkColorCodesMessage $message): void
    {
        $codeRepo = $this->em->getRepository(BrickLinkColorCode::class);
        $partRepo = $this->em->getRepository(BrickLinkPart::class);

        $document = XMLDocument::createFromFile($message->xmlFile);
        $items = $document->getElementsByTagName('ITEM');

        $matrix = [];
        foreach ($items as $code) {
            $itemId = $this->getTextContent($code, 'ITEMID');
            $color = $this->getTextContent($code, 'COLOR');

            $key = sprintf('%s-%s', $itemId, $color);
            if (!isset($matrix[$key])) {
                $matrix[$key] = [
                    'partId'     => $itemId,
                    'colorName'  => $color,
                    'elementIds' => [],
                ];
            }
            $matrix[$key]['elementIds'][] = $this->getTextContent($code, 'CODENAME');
        }

        foreach ($matrix as $data) {
            $part = $this->getPart($data['partId']);
            $color = $this->getColor($data['colorName']);
            $itemId = sprintf('%s-%02d', $part->part, $color->id);

            $entity = $codeRepo->findOneBy(['itemId' => $itemId]);
            if (!$entity) {
                $entity = new BrickLinkColorCode();
                $entity->itemId = $itemId;
                $this->em->persist($entity);
            }

            $entity->part = $part;
            $entity->color = $color;
            $entity->elementIds = $data['elementIds'];
        }

        $this->em->flush();
    }

    protected function getPart(string $partId): BrickLinkPart
    {
        if (empty($this->parts)) {
            $parts = $this->em->getRepository(BrickLinkPart::class)->findAll();
            foreach ($parts as $part) {
                $this->parts[$part->part] = $part;
            }
        }

        return $this->parts[$partId];
    }

    protected function getColor(string $colorName): BrickLinkColor
    {
        if (empty($this->colors)) {
            $colors = $this->em->getRepository(BrickLinkColor::class)->findAll();
            foreach ($colors as $color) {
                $this->colors[$color->name] = $color;
            }
        }

        return $this->colors[$colorName];
    }
}

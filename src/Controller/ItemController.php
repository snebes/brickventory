<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/items', name: 'api_items_')]
class ItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->entityManager->getRepository(Item::class)->findAll();
        
        $data = array_map(function (Item $item) {
            return [
                'id' => $item->id,
                'itemId' => $item->itemId,
                'itemName' => $item->itemName,
                'quantityAvailable' => $item->quantityAvailable,
                'quantityOnHand' => $item->quantityOnHand,
                'quantityOnOrder' => $item->quantityOnOrder,
                'quantityCommitted' => $item->quantityCommitted,
            ];
        }, $items);

        return $this->json($data);
    }
}

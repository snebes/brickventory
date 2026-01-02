<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/items', name: 'api_items_')]
class ItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        $search = $request->query->get('search', '');
        
        $repository = $this->entityManager->getRepository(Item::class);
        $queryBuilder = $repository->createQueryBuilder('i');
        
        if (!empty($search)) {
            $queryBuilder
                ->where('i.itemId LIKE :search OR i.itemName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        $totalQuery = clone $queryBuilder;
        $total = (int) $totalQuery->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();
        
        $items = $queryBuilder
            ->orderBy('i.itemId', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
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

        return $this->json([
            'items' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($page * $limit) < $total
        ]);
    }
}

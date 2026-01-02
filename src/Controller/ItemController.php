<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/items', name: 'api_items_')]
class ItemController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
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
                'quantityBackOrdered' => $item->quantityBackOrdered,
                'quantityCommitted' => $item->quantityCommitted,
                'partId' => $item->partId,
                'colorId' => $item->colorId,
                'elementIds' => $item->elementIds,
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

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $item = $this->entityManager->getRepository(Item::class)->find($id);
        
        if (!$item) {
            return $this->json(['error' => 'Item not found'], 404);
        }
        
        return $this->json([
            'id' => $item->id,
            'itemId' => $item->itemId,
            'itemName' => $item->itemName,
            'quantityAvailable' => $item->quantityAvailable,
            'quantityOnHand' => $item->quantityOnHand,
            'quantityOnOrder' => $item->quantityOnOrder,
            'quantityBackOrdered' => $item->quantityBackOrdered,
            'quantityCommitted' => $item->quantityCommitted,
            'partId' => $item->partId,
            'colorId' => $item->colorId,
            'elementIds' => $item->elementIds,
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }
        
        // Get or create a default category
        $categoryRepository = $this->entityManager->getRepository(ItemCategory::class);
        $category = $categoryRepository->findOneBy([]);
        
        if (!$category) {
            $category = new ItemCategory();
            $category->name = 'Default';
            $this->entityManager->persist($category);
            $this->entityManager->flush();
        }
        
        $item = new Item();
        $item->itemId = $data['itemId'] ?? '';
        $item->itemName = $data['itemName'] ?? '';
        $item->quantityOnHand = (int) ($data['quantityOnHand'] ?? 0);
        $item->quantityOnOrder = (int) ($data['quantityOnOrder'] ?? 0);
        $item->quantityBackOrdered = (int) ($data['quantityBackOrdered'] ?? 0);
        $item->quantityCommitted = (int) ($data['quantityCommitted'] ?? 0);
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;
        $item->partId = $data['partId'] ?? '';
        $item->colorId = $data['colorId'] ?? '';
        $item->elementIds = $data['elementIds'] ?? '';
        $item->category = $category;
        
        $errors = $this->validator->validate($item);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], 400);
        }
        
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        
        return $this->json([
            'id' => $item->id,
            'itemId' => $item->itemId,
            'itemName' => $item->itemName,
            'quantityAvailable' => $item->quantityAvailable,
            'quantityOnHand' => $item->quantityOnHand,
            'quantityOnOrder' => $item->quantityOnOrder,
            'quantityBackOrdered' => $item->quantityBackOrdered,
            'quantityCommitted' => $item->quantityCommitted,
            'partId' => $item->partId,
            'colorId' => $item->colorId,
            'elementIds' => $item->elementIds,
        ], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->entityManager->getRepository(Item::class)->find($id);
        
        if (!$item) {
            return $this->json(['error' => 'Item not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }
        
        if (isset($data['itemName'])) {
            $item->itemName = $data['itemName'];
        }
        if (isset($data['quantityOnHand'])) {
            $item->quantityOnHand = (int) $data['quantityOnHand'];
        }
        if (isset($data['quantityOnOrder'])) {
            $item->quantityOnOrder = (int) $data['quantityOnOrder'];
        }
        if (isset($data['quantityBackOrdered'])) {
            $item->quantityBackOrdered = (int) $data['quantityBackOrdered'];
        }
        if (isset($data['quantityCommitted'])) {
            $item->quantityCommitted = (int) $data['quantityCommitted'];
        }
        if (isset($data['partId'])) {
            $item->partId = $data['partId'];
        }
        if (isset($data['colorId'])) {
            $item->colorId = $data['colorId'];
        }
        if (isset($data['elementIds'])) {
            $item->elementIds = $data['elementIds'];
        }
        
        // Recalculate available quantity
        $item->quantityAvailable = $item->quantityOnHand - $item->quantityCommitted;
        
        $errors = $this->validator->validate($item);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], 400);
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'id' => $item->id,
            'itemId' => $item->itemId,
            'itemName' => $item->itemName,
            'quantityAvailable' => $item->quantityAvailable,
            'quantityOnHand' => $item->quantityOnHand,
            'quantityOnOrder' => $item->quantityOnOrder,
            'quantityBackOrdered' => $item->quantityBackOrdered,
            'quantityCommitted' => $item->quantityCommitted,
            'partId' => $item->partId,
            'colorId' => $item->colorId,
            'elementIds' => $item->elementIds,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->entityManager->getRepository(Item::class)->find($id);
        
        if (!$item) {
            return $this->json(['error' => 'Item not found'], 404);
        }
        
        $this->entityManager->remove($item);
        $this->entityManager->flush();
        
        return $this->json(['message' => 'Item deleted successfully']);
    }
}

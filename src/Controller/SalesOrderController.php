<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\SalesOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sales-orders', name: 'api_sales_orders_')]
class SalesOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $salesOrders = $this->entityManager->getRepository(SalesOrder::class)->findAll();
        
        $data = array_map(function (SalesOrder $so) {
            return [
                'id' => $so->id,
                'orderNumber' => $so->orderNumber,
                'orderDate' => $so->orderDate->format('Y-m-d H:i:s'),
                'status' => $so->status,
                'notes' => $so->notes,
                'lines' => array_map(function (SalesOrderLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityOrdered' => $line->quantityOrdered,
                        'quantityFulfilled' => $line->quantityFulfilled,
                    ];
                }, $so->lines->toArray()),
            ];
        }, $salesOrders);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($id);
        
        if (!$so) {
            return $this->json(['error' => 'Sales order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $so->id,
            'orderNumber' => $so->orderNumber,
            'orderDate' => $so->orderDate->format('Y-m-d H:i:s'),
            'status' => $so->status,
            'notes' => $so->notes,
            'lines' => array_map(function (SalesOrderLine $line) {
                return [
                    'id' => $line->id,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'quantityOrdered' => $line->quantityOrdered,
                    'quantityFulfilled' => $line->quantityFulfilled,
                ];
            }, $so->lines->toArray()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $so = new SalesOrder();
        $so->orderNumber = $data['orderNumber'] ?? 'SO-' . date('YmdHis');
        $so->orderDate = isset($data['orderDate']) ? new \DateTime($data['orderDate']) : new \DateTime();
        $so->status = $data['status'] ?? 'pending';
        $so->notes = $data['notes'] ?? null;

        if (!empty($data['lines'])) {
            foreach ($data['lines'] as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                if (!$item) {
                    return $this->json(['error' => 'Item not found: ' . $lineData['itemId']], Response::HTTP_BAD_REQUEST);
                }

                $line = new SalesOrderLine();
                $line->salesOrder = $so;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                
                $so->lines->add($line);
            }
        }

        $this->entityManager->persist($so);
        $this->entityManager->flush();

        // Dispatch event to update inventory
        $this->eventDispatcher->dispatch(new SalesOrderCreatedEvent($so));

        return $this->json([
            'id' => $so->id,
            'orderNumber' => $so->orderNumber,
            'message' => 'Sales order created successfully'
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($id);
        
        if (!$so) {
            return $this->json(['error' => 'Sales order not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Update basic fields
        if (isset($data['orderDate'])) {
            $so->orderDate = new \DateTime($data['orderDate']);
        }
        if (isset($data['status'])) {
            $so->status = $data['status'];
        }
        if (isset($data['notes'])) {
            $so->notes = $data['notes'];
        }

        // Update lines if provided
        if (isset($data['lines'])) {
            // Remove existing lines
            foreach ($so->lines as $line) {
                $this->entityManager->remove($line);
            }
            $so->lines->clear();

            // Add new lines
            foreach ($data['lines'] as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                if (!$item) {
                    return $this->json(['error' => 'Item not found: ' . $lineData['itemId']], Response::HTTP_BAD_REQUEST);
                }

                $line = new SalesOrderLine();
                $line->salesOrder = $so;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                
                $so->lines->add($line);
                $this->entityManager->persist($line);
            }
        }

        $this->entityManager->flush();

        // Re-dispatch event to update inventory
        $this->eventDispatcher->dispatch(new SalesOrderCreatedEvent($so));

        return $this->json([
            'id' => $so->id,
            'message' => 'Sales order updated successfully'
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($id);
        
        if (!$so) {
            return $this->json(['error' => 'Sales order not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($so);
        $this->entityManager->flush();

        return $this->json(['message' => 'Sales order deleted successfully']);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\SalesOrderCreatedEvent;
use App\Event\SalesOrderUpdatedEvent;
use App\Event\SalesOrderDeletedEvent;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $orderDateFrom = $request->query->get('orderDateFrom');
        $orderDateTo = $request->query->get('orderDateTo');
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('perPage', 100);

        $qb = $this->entityManager
            ->getRepository(SalesOrder::class)
            ->createQueryBuilder('so')
            ->orderBy('so.orderDate', 'DESC');

        if ($status) {
            $qb->andWhere('so.status = :status')
               ->setParameter('status', $status);
        }

        if ($orderDateFrom) {
            $qb->andWhere('so.orderDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($orderDateFrom));
        }

        if ($orderDateTo) {
            $qb->andWhere('so.orderDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime($orderDateTo));
        }

        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $salesOrders = $qb->getQuery()->getResult();

        $result = array_map(function (SalesOrder $so) {
            return $this->serializeSalesOrder($so);
        }, $salesOrders);

        return $this->json($result);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $so = $this->entityManager->getRepository(SalesOrder::class)->find($id);
        
        if (!$so) {
            return $this->json(['error' => 'Sales order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeSalesOrder($so));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $so = new SalesOrder();
            if (!empty($data['orderNumber'])) {
                $so->orderNumber = $data['orderNumber'];
            }
            $so->orderDate = new \DateTime($data['orderDate'] ?? 'now');
            $so->status = $data['status'] ?? 'pending';
            $so->notes = $data['notes'] ?? null;

            $lines = $data['lines'] ?? [];
            foreach ($lines as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                
                if (!$item) {
                    throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
                }
                
                $line = new SalesOrderLine();
                $line->salesOrder = $so;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                
                $so->lines->add($line);
            }

            $this->entityManager->persist($so);
            $this->entityManager->flush();

            // Dispatch event to update inventory (event sourcing)
            $this->eventDispatcher->dispatch(new SalesOrderCreatedEvent($so));

            return $this->json([
                'id' => $so->id,
                'message' => 'Sales order created successfully'
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $so = $this->entityManager->getRepository(SalesOrder::class)->find($id);
            
            if (!$so) {
                throw new \InvalidArgumentException("Sales order {$id} not found");
            }

            // Capture previous state for event sourcing
            $previousState = $this->serializeSalesOrder($so);

            // Update basic fields
            $so->orderDate = new \DateTime($data['orderDate']);
            $so->status = $data['status'];
            $so->notes = $data['notes'] ?? null;

            // Remove existing lines
            foreach ($so->lines as $line) {
                $this->entityManager->remove($line);
            }
            $so->lines->clear();

            // Add new lines
            $lines = $data['lines'] ?? [];
            foreach ($lines as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                
                if (!$item) {
                    throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
                }
                
                $line = new SalesOrderLine();
                $line->salesOrder = $so;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                
                $so->lines->add($line);
            }

            $this->entityManager->flush();

            // Dispatch event for event sourcing
            $this->eventDispatcher->dispatch(new SalesOrderUpdatedEvent($so, $previousState));

            return $this->json([
                'id' => $id,
                'message' => 'Sales order updated successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $so = $this->entityManager->getRepository(SalesOrder::class)->find($id);
            
            if (!$so) {
                throw new \InvalidArgumentException("Sales order {$id} not found");
            }

            // Capture state before deletion for event sourcing
            $orderState = $this->serializeSalesOrder($so);
            $orderId = $so->id;

            $this->entityManager->remove($so);
            $this->entityManager->flush();

            // Dispatch event for event sourcing
            $this->eventDispatcher->dispatch(new SalesOrderDeletedEvent($orderId, $orderState));

            return $this->json(['message' => 'Sales order deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    private function serializeSalesOrder(SalesOrder $so): array
    {
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
    }
}


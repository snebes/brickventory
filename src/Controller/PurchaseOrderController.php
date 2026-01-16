<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/purchase-orders', name: 'api_purchase_orders_')]
class PurchaseOrderController extends AbstractController
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
            ->getRepository(PurchaseOrder::class)
            ->createQueryBuilder('po')
            ->orderBy('po.orderDate', 'DESC');

        if ($status) {
            $qb->andWhere('po.status = :status')
               ->setParameter('status', $status);
        }

        if ($orderDateFrom) {
            $qb->andWhere('po.orderDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($orderDateFrom));
        }

        if ($orderDateTo) {
            $qb->andWhere('po.orderDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime($orderDateTo));
        }

        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $purchaseOrders = $qb->getQuery()->getResult();

        $result = array_map(function (PurchaseOrder $po) {
            return [
                'id' => $po->id,
                'orderNumber' => $po->orderNumber,
                'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
                'status' => $po->status,
                'reference' => $po->reference,
                'notes' => $po->notes,
                'lines' => array_map(function (PurchaseOrderLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityOrdered' => $line->quantityOrdered,
                        'quantityReceived' => $line->quantityReceived,
                        'rate' => $line->rate,
                    ];
                }, $po->lines->toArray()),
            ];
        }, $purchaseOrders);

        return $this->json($result);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
        
        if (!$po) {
            return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $po->id,
            'orderNumber' => $po->orderNumber,
            'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
            'status' => $po->status,
            'reference' => $po->reference,
            'notes' => $po->notes,
            'lines' => array_map(function (PurchaseOrderLine $line) {
                return [
                    'id' => $line->id,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'quantityOrdered' => $line->quantityOrdered,
                    'quantityReceived' => $line->quantityReceived,
                    'rate' => $line->rate,
                ];
            }, $po->lines->toArray()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $po = new PurchaseOrder();
            $po->orderNumber = $data['orderNumber'] ?? 'PO-' . date('YmdHis');
            $po->orderDate = new \DateTime($data['orderDate'] ?? 'now');
            $po->status = $data['status'] ?? 'pending';
            $po->reference = $data['reference'] ?? null;
            $po->notes = $data['notes'] ?? null;

            $lines = $data['lines'] ?? [];
            foreach ($lines as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                
                if (!$item) {
                    throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
                }
                
                $line = new PurchaseOrderLine();
                $line->purchaseOrder = $po;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                $line->rate = $lineData['rate'];
                
                $po->lines->add($line);
            }

            $this->entityManager->persist($po);
            $this->entityManager->flush();

            // Dispatch event to update inventory (event sourcing)
            $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($po));

            return $this->json([
                'id' => $po->id,
                'message' => 'Purchase order created successfully'
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
            $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
            
            if (!$po) {
                throw new \InvalidArgumentException("Purchase order {$id} not found");
            }

            // Update basic fields
            $po->orderDate = new \DateTime($data['orderDate']);
            $po->status = $data['status'];
            $po->reference = $data['reference'] ?? null;
            $po->notes = $data['notes'] ?? null;

            // Remove existing lines
            foreach ($po->lines as $line) {
                $this->entityManager->remove($line);
            }
            $po->lines->clear();

            // Add new lines
            $lines = $data['lines'] ?? [];
            foreach ($lines as $lineData) {
                $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
                
                if (!$item) {
                    throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
                }
                
                $line = new PurchaseOrderLine();
                $line->purchaseOrder = $po;
                $line->item = $item;
                $line->quantityOrdered = $lineData['quantityOrdered'];
                $line->rate = $lineData['rate'];
                
                $po->lines->add($line);
            }

            $this->entityManager->flush();

            return $this->json([
                'id' => $id,
                'message' => 'Purchase order updated successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
            
            if (!$po) {
                throw new \InvalidArgumentException("Purchase order {$id} not found");
            }

            $this->entityManager->remove($po);
            $this->entityManager->flush();

            return $this->json(['message' => 'Purchase order deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}

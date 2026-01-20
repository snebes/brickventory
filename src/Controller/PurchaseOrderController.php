<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use App\Event\PurchaseOrderUpdatedEvent;
use App\Event\PurchaseOrderDeletedEvent;
use App\Service\PurchaseOrderService;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PurchaseOrderService $purchaseOrderService
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
            return $this->serializePurchaseOrder($po);
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

        return $this->json($this->serializePurchaseOrder($po));
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

            // Capture previous state for event sourcing
            $previousState = $this->serializePurchaseOrder($po);

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

            // Dispatch event for event sourcing
            $this->eventDispatcher->dispatch(new PurchaseOrderUpdatedEvent($po, $previousState));

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

            // Capture state before deletion for event sourcing
            $orderState = $this->serializePurchaseOrder($po);
            $orderId = $po->id;

            $this->entityManager->remove($po);
            $this->entityManager->flush();

            // Dispatch event for event sourcing
            $this->eventDispatcher->dispatch(new PurchaseOrderDeletedEvent($orderId, $orderState));

            return $this->json(['message' => 'Purchase order deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    private function serializePurchaseOrder(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'orderNumber' => $po->orderNumber,
            'orderDate' => $po->orderDate->format('Y-m-d H:i:s'),
            'status' => $po->status,
            'reference' => $po->reference,
            'notes' => $po->notes,
            'vendor' => $po->vendor ? [
                'id' => $po->vendor->id,
                'vendorCode' => $po->vendor->vendorCode,
                'vendorName' => $po->vendor->vendorName,
            ] : null,
            'expectedReceiptDate' => $po->expectedReceiptDate?->format('Y-m-d'),
            'paymentTerms' => $po->paymentTerms,
            'currency' => $po->currency,
            'subtotal' => $po->subtotal,
            'taxTotal' => $po->taxTotal,
            'shippingCost' => $po->shippingCost,
            'total' => $po->total,
            'approvedBy' => $po->approvedBy,
            'approvedAt' => $po->approvedAt?->format('Y-m-d H:i:s'),
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
                    'quantityBilled' => $line->quantityBilled,
                    'rate' => $line->rate,
                    'closed' => $line->closed,
                    'closedReason' => $line->closedReason,
                ];
            }, $po->lines->toArray()),
        ];
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
            
            if (!$po) {
                return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
            }

            $approverId = $data['approverId'] ?? 1; // TODO: Get from authenticated user
            
            $this->purchaseOrderService->approvePurchaseOrder($po, $approverId);

            return $this->json([
                'id' => $id,
                'message' => 'Purchase order approved successfully',
                'status' => $po->status
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/close', name: 'close', methods: ['POST'])]
    public function close(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
            
            if (!$po) {
                return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
            }

            $reason = $data['reason'] ?? 'Closed';
            
            $this->purchaseOrderService->closePurchaseOrder($po, $reason);

            return $this->json([
                'id' => $id,
                'message' => 'Purchase order closed successfully',
                'status' => $po->status
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/receipt-status', name: 'receipt_status', methods: ['GET'])]
    public function getReceiptStatus(int $id): JsonResponse
    {
        try {
            $po = $this->entityManager->getRepository(PurchaseOrder::class)->find($id);
            
            if (!$po) {
                return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
            }

            $lineStatus = array_map(function (PurchaseOrderLine $line) {
                return [
                    'lineId' => $line->id,
                    'itemId' => $line->item->id,
                    'itemName' => $line->item->itemName,
                    'quantityOrdered' => $line->quantityOrdered,
                    'quantityReceived' => $line->quantityReceived,
                    'quantityRemaining' => $line->getRemainingQuantity(),
                    'fullyReceived' => $line->isFullyReceived(),
                    'closed' => $line->closed,
                ];
            }, $po->lines->toArray());

            return $this->json([
                'purchaseOrderId' => $po->id,
                'orderNumber' => $po->orderNumber,
                'status' => $po->status,
                'lines' => $lineStatus,
                'fullyReceived' => $this->purchaseOrderService->isFullyReceived($po),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}

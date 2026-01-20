<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\Location;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Entity\Vendor;
use App\Event\PurchaseOrderCreatedEvent;
use App\Event\PurchaseOrderUpdatedEvent;
use App\Event\PurchaseOrderDeletedEvent;
use App\Repository\PurchaseOrderRepository;
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
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly PurchaseOrderRepository $purchaseOrderRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $vendorId = $request->query->get('vendorId');
        $orderDateFrom = $request->query->get('orderDateFrom');
        $orderDateTo = $request->query->get('orderDateTo');
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('perPage', 100);

        $qb = $this->entityManager
            ->getRepository(PurchaseOrder::class)
            ->createQueryBuilder('po')
            ->leftJoin('po.vendor', 'v')
            ->leftJoin('po.location', 'l')
            ->orderBy('po.orderDate', 'DESC');

        if ($status) {
            $qb->andWhere('po.status = :status')
               ->setParameter('status', $status);
        }

        if ($vendorId) {
            $qb->andWhere('po.vendor = :vendorId')
               ->setParameter('vendorId', (int) $vendorId);
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
            // Validate vendor_id is provided (required per NetSuite ERP model)
            if (!isset($data['vendorId']) || empty($data['vendorId'])) {
                return $this->json([
                    'error' => 'Vendor is required. Please select a vendor before saving the Purchase Order.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find and validate the vendor
            $vendor = $this->entityManager->getRepository(Vendor::class)->find($data['vendorId']);
            if (!$vendor) {
                return $this->json([
                    'error' => 'Invalid vendor specified'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate vendor is active
            if (!$vendor->active) {
                return $this->json([
                    'error' => 'The selected vendor is inactive. Please choose an active vendor.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate location (required)
            if (!isset($data['locationId']) || empty($data['locationId'])) {
                return $this->json([
                    'error' => 'Receiving location is required. Please select a location before saving the Purchase Order.'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find and validate the location
            $location = $this->entityManager->getRepository(Location::class)->find($data['locationId']);
            if (!$location) {
                return $this->json([
                    'error' => 'Invalid location specified'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate location is active and can receive
            if (!$location->active) {
                return $this->json([
                    'error' => 'The selected location is inactive. Please choose an active location.'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$location->canReceiveInventory()) {
                return $this->json([
                    'error' => 'The selected location is not configured to receive inventory. Please choose a receiving location.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $po = new PurchaseOrder();
            $po->vendor = $vendor;
            $po->location = $location;
            $po->orderNumber = $data['orderNumber'] ?? $this->purchaseOrderRepository->getNextOrderNumber();
            $po->orderDate = new \DateTime($data['orderDate'] ?? 'now');
            $po->status = $data['status'] ?? 'Pending Approval';
            $po->reference = $data['reference'] ?? null;
            $po->notes = $data['notes'] ?? null;

            // Auto-populate vendor defaults if not provided
            $po->paymentTerms = $data['paymentTerms'] ?? $vendor->defaultPaymentTerms;
            $po->currency = $data['currency'] ?? $vendor->defaultCurrency;
            $po->billToAddress = $data['billToAddress'] ?? $vendor->billingAddress;

            // Handle expected receipt date
            if (isset($data['expectedReceiptDate'])) {
                $po->expectedReceiptDate = new \DateTime($data['expectedReceiptDate']);
            }

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

            // Calculate totals
            $po->calculateTotals();

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

            // Handle vendor change
            if (isset($data['vendorId'])) {
                $newVendorId = (int) $data['vendorId'];
                $currentVendorId = $po->vendor->id ?? null;

                if ($newVendorId !== $currentVendorId) {
                    // Check if vendor change is allowed based on PO status
                    $approvedStatuses = ['Pending Receipt', 'Partially Received', 'Fully Received', 'Closed'];
                    if (in_array($po->status, $approvedStatuses, true)) {
                        return $this->json([
                            'error' => 'Vendor cannot be changed after Purchase Order approval.'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    // Validate the new vendor
                    $newVendor = $this->entityManager->getRepository(Vendor::class)->find($newVendorId);
                    if (!$newVendor) {
                        return $this->json([
                            'error' => 'Invalid vendor specified'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    if (!$newVendor->active) {
                        return $this->json([
                            'error' => 'The selected vendor is inactive. Please choose an active vendor.'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    $po->vendor = $newVendor;
                }
            }

            // Handle location change
            if (isset($data['locationId'])) {
                $newLocationId = (int) $data['locationId'];
                $currentLocationId = $po->location?->id ?? null;

                if ($newLocationId !== $currentLocationId) {
                    // Check if location change is allowed (not after items received)
                    $hasReceipts = $po->status === 'Partially Received' || $po->status === 'Fully Received';
                    if ($hasReceipts) {
                        return $this->json([
                            'error' => 'Location cannot be changed after items have been received.'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    // Validate the new location
                    $newLocation = $this->entityManager->getRepository(Location::class)->find($newLocationId);
                    if (!$newLocation) {
                        return $this->json([
                            'error' => 'Invalid location specified'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    if (!$newLocation->active || !$newLocation->canReceiveInventory()) {
                        return $this->json([
                            'error' => 'The selected location cannot receive inventory.'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    $po->location = $newLocation;
                }
            }

            // Capture previous state for event sourcing
            $previousState = $this->serializePurchaseOrder($po);

            // Update basic fields
            $po->orderDate = new \DateTime($data['orderDate']);
            $po->status = $data['status'];
            $po->reference = $data['reference'] ?? null;
            $po->notes = $data['notes'] ?? null;

            // Update optional fields
            if (isset($data['paymentTerms'])) {
                $po->paymentTerms = $data['paymentTerms'];
            }
            if (isset($data['currency'])) {
                $po->currency = $data['currency'];
            }
            if (isset($data['expectedReceiptDate'])) {
                $po->expectedReceiptDate = $data['expectedReceiptDate'] ? new \DateTime($data['expectedReceiptDate']) : null;
            }

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

            // Recalculate totals
            $po->calculateTotals();

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
            'vendor' => [
                'id' => $po->vendor->id,
                'vendorCode' => $po->vendor->vendorCode,
                'vendorName' => $po->vendor->vendorName,
                'defaultPaymentTerms' => $po->vendor->defaultPaymentTerms,
                'defaultCurrency' => $po->vendor->defaultCurrency,
            ],
            'location' => [
                'id' => $po->location->id,
                'locationCode' => $po->location->locationCode,
                'locationName' => $po->location->locationName,
            ],
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

            $approverId = $data['approverId'] ?? null;
            
            if (!$approverId) {
                return $this->json(['error' => 'approverId is required'], Response::HTTP_BAD_REQUEST);
            }
            
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

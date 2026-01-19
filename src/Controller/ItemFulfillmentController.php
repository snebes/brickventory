<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemFulfillment;
use App\Entity\ItemFulfillmentLine;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderLine;
use App\Event\ItemFulfillmentCreatedEvent;
use App\Event\ItemShippedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for Item Fulfillment operations following NetSuite workflow.
 * 
 * Supports: Create fulfillment, list fulfillments, get fulfillment details,
 * mark as shipped, and list pending fulfillments.
 */
#[Route('/api/fulfillments', name: 'api_fulfillments_')]
class ItemFulfillmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * List all fulfillments with optional filtering.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $salesOrderId = $request->query->get('salesOrderId');
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('perPage', 100);

        $qb = $this->entityManager
            ->getRepository(ItemFulfillment::class)
            ->createQueryBuilder('f')
            ->orderBy('f.fulfillmentDate', 'DESC');

        if ($status) {
            $qb->andWhere('f.status = :status')
               ->setParameter('status', $status);
        }

        if ($salesOrderId) {
            $qb->andWhere('f.salesOrder = :salesOrderId')
               ->setParameter('salesOrderId', $salesOrderId);
        }

        $qb->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $fulfillments = $qb->getQuery()->getResult();

        $result = array_map(function (ItemFulfillment $f) {
            return $this->serializeFulfillment($f);
        }, $fulfillments);

        return $this->json($result);
    }

    /**
     * Get a specific fulfillment by ID.
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $fulfillment = $this->entityManager->getRepository(ItemFulfillment::class)->find($id);

        if (!$fulfillment) {
            return $this->json(['error' => 'Fulfillment not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeFulfillment($fulfillment));
    }

    /**
     * Create a new fulfillment for a sales order.
     * 
     * Request body:
     * {
     *   "salesOrderId": 123,
     *   "shipMethod": "FedEx Ground",
     *   "trackingNumber": "1234567890",
     *   "notes": "Optional notes",
     *   "lines": [
     *     {
     *       "salesOrderLineId": 456,
     *       "quantityFulfilled": 10,
     *       "serialNumbers": ["SN001", "SN002"],
     *       "lotNumber": "LOT123",
     *       "binLocation": "A1-B2"
     *     }
     *   ]
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['salesOrderId'])) {
            return $this->json(['error' => 'salesOrderId is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            return $this->json(['error' => 'lines array is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $salesOrder = $this->entityManager->getRepository(SalesOrder::class)->find($data['salesOrderId']);

            if (!$salesOrder) {
                throw new \InvalidArgumentException("Sales order {$data['salesOrderId']} not found");
            }

            // Validate sales order can be fulfilled
            if (!$salesOrder->canBeFulfilled()) {
                throw new \InvalidArgumentException(
                    "Sales order cannot be fulfilled. Current status: {$salesOrder->status}"
                );
            }

            // Create fulfillment header
            $fulfillment = new ItemFulfillment();
            $fulfillment->salesOrder = $salesOrder;
            $fulfillment->status = ItemFulfillment::STATUS_PICKED;
            $fulfillment->shipMethod = $data['shipMethod'] ?? null;
            $fulfillment->trackingNumber = $data['trackingNumber'] ?? null;
            $fulfillment->notes = $data['notes'] ?? null;

            if (!empty($data['fulfillmentDate'])) {
                $fulfillment->fulfillmentDate = new \DateTime($data['fulfillmentDate']);
            }

            // Process fulfillment lines
            foreach ($data['lines'] as $lineData) {
                if (empty($lineData['salesOrderLineId'])) {
                    throw new \InvalidArgumentException('salesOrderLineId is required for each line');
                }

                if (empty($lineData['quantityFulfilled']) || $lineData['quantityFulfilled'] <= 0) {
                    throw new \InvalidArgumentException('quantityFulfilled must be greater than 0');
                }

                $salesOrderLine = $this->entityManager
                    ->getRepository(SalesOrderLine::class)
                    ->find($lineData['salesOrderLineId']);

                if (!$salesOrderLine) {
                    throw new \InvalidArgumentException(
                        "Sales order line {$lineData['salesOrderLineId']} not found"
                    );
                }

                // Verify the sales order line belongs to the specified sales order
                if ($salesOrderLine->salesOrder->id !== $salesOrder->id) {
                    throw new \InvalidArgumentException(
                        "Sales order line {$lineData['salesOrderLineId']} does not belong to sales order {$salesOrder->id}"
                    );
                }

                // Validate quantity
                $quantityRemaining = $salesOrderLine->getQuantityRemaining();
                if ($lineData['quantityFulfilled'] > $quantityRemaining) {
                    throw new \InvalidArgumentException(
                        "Cannot fulfill {$lineData['quantityFulfilled']} units for line {$salesOrderLine->id}. " .
                        "Only {$quantityRemaining} units remaining."
                    );
                }

                // Create fulfillment line
                $fulfillmentLine = new ItemFulfillmentLine();
                $fulfillmentLine->itemFulfillment = $fulfillment;
                $fulfillmentLine->salesOrderLine = $salesOrderLine;
                $fulfillmentLine->item = $salesOrderLine->item;
                $fulfillmentLine->quantityFulfilled = $lineData['quantityFulfilled'];
                $fulfillmentLine->serialNumbers = $lineData['serialNumbers'] ?? null;
                $fulfillmentLine->lotNumber = $lineData['lotNumber'] ?? null;
                $fulfillmentLine->binLocation = $lineData['binLocation'] ?? null;

                $fulfillment->lines->add($fulfillmentLine);
            }

            $this->entityManager->persist($fulfillment);
            $this->entityManager->flush();

            // Dispatch event to update inventory (event sourcing)
            $this->eventDispatcher->dispatch(new ItemFulfillmentCreatedEvent($fulfillment));

            return $this->json([
                'id' => $fulfillment->id,
                'fulfillmentNumber' => $fulfillment->fulfillmentNumber,
                'message' => 'Fulfillment created successfully'
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Mark a fulfillment as shipped.
     * 
     * Request body:
     * {
     *   "trackingNumber": "1234567890",
     *   "shipMethod": "FedEx Ground"
     * }
     */
    #[Route('/{id}/ship', name: 'ship', methods: ['POST'])]
    public function ship(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $fulfillment = $this->entityManager->getRepository(ItemFulfillment::class)->find($id);

            if (!$fulfillment) {
                throw new \InvalidArgumentException("Fulfillment {$id} not found");
            }

            // Validate fulfillment can be shipped
            if ($fulfillment->isShipped()) {
                throw new \InvalidArgumentException(
                    "Fulfillment is already shipped. Current status: {$fulfillment->status}"
                );
            }

            // Dispatch event to mark as shipped
            $this->eventDispatcher->dispatch(new ItemShippedEvent(
                $fulfillment,
                $data['trackingNumber'] ?? null,
                $data['shipMethod'] ?? null
            ));

            return $this->json([
                'id' => $fulfillment->id,
                'fulfillmentNumber' => $fulfillment->fulfillmentNumber,
                'status' => $fulfillment->status,
                'trackingNumber' => $fulfillment->trackingNumber,
                'shippedAt' => $fulfillment->shippedAt?->format('Y-m-d H:i:s'),
                'message' => 'Fulfillment marked as shipped'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * List pending fulfillments (not yet shipped).
     */
    #[Route('/pending', name: 'pending', methods: ['GET'], priority: 1)]
    public function pending(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('perPage', 100);

        $fulfillments = $this->entityManager
            ->getRepository(ItemFulfillment::class)
            ->createQueryBuilder('f')
            ->where('f.status IN (:statuses)')
            ->setParameter('statuses', [ItemFulfillment::STATUS_PICKED, ItemFulfillment::STATUS_PACKED])
            ->orderBy('f.fulfillmentDate', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $result = array_map(function (ItemFulfillment $f) {
            return $this->serializeFulfillment($f);
        }, $fulfillments);

        return $this->json($result);
    }

    /**
     * List fulfillments for a specific sales order.
     */
    #[Route('/by-sales-order/{salesOrderId}', name: 'by_sales_order', methods: ['GET'])]
    public function bySalesOrder(int $salesOrderId): JsonResponse
    {
        $salesOrder = $this->entityManager->getRepository(SalesOrder::class)->find($salesOrderId);

        if (!$salesOrder) {
            return $this->json(['error' => 'Sales order not found'], Response::HTTP_NOT_FOUND);
        }

        $fulfillments = $this->entityManager
            ->getRepository(ItemFulfillment::class)
            ->createQueryBuilder('f')
            ->where('f.salesOrder = :salesOrder')
            ->setParameter('salesOrder', $salesOrder)
            ->orderBy('f.fulfillmentDate', 'DESC')
            ->getQuery()
            ->getResult();

        $result = array_map(function (ItemFulfillment $f) {
            return $this->serializeFulfillment($f);
        }, $fulfillments);

        return $this->json($result);
    }

    private function serializeFulfillment(ItemFulfillment $f): array
    {
        return [
            'id' => $f->id,
            'fulfillmentNumber' => $f->fulfillmentNumber,
            'salesOrder' => [
                'id' => $f->salesOrder->id,
                'orderNumber' => $f->salesOrder->orderNumber,
            ],
            'fulfillmentDate' => $f->fulfillmentDate->format('Y-m-d H:i:s'),
            'status' => $f->status,
            'shipMethod' => $f->shipMethod,
            'trackingNumber' => $f->trackingNumber,
            'shippingCost' => $f->shippingCost,
            'shippedAt' => $f->shippedAt?->format('Y-m-d H:i:s'),
            'notes' => $f->notes,
            'lines' => array_map(function (ItemFulfillmentLine $line) {
                return [
                    'id' => $line->id,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'salesOrderLine' => [
                        'id' => $line->salesOrderLine->id,
                        'quantityOrdered' => $line->salesOrderLine->quantityOrdered,
                        'quantityFulfilled' => $line->salesOrderLine->quantityFulfilled,
                    ],
                    'quantityFulfilled' => $line->quantityFulfilled,
                    'serialNumbers' => $line->serialNumbers,
                    'lotNumber' => $line->lotNumber,
                    'binLocation' => $line->binLocation,
                ];
            }, $f->lines->toArray()),
        ];
    }
}

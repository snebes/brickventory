<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReceipt;
use App\Entity\ItemReceiptLine;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\ItemReceivedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/item-receipts', name: 'api_item_receipts_')]
class ItemReceiptController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $receipts = $this->entityManager->getRepository(ItemReceipt::class)->findAll();
        
        $data = array_map(function (ItemReceipt $receipt) {
            return [
                'id' => $receipt->id,
                'uuid' => $receipt->uuid,
                'purchaseOrder' => [
                    'id' => $receipt->purchaseOrder->id,
                    'orderNumber' => $receipt->purchaseOrder->orderNumber,
                    'reference' => $receipt->purchaseOrder->reference,
                ],
                'receiptDate' => $receipt->receiptDate->format('Y-m-d H:i:s'),
                'status' => $receipt->status,
                'notes' => $receipt->notes,
                'lines' => array_map(function (ItemReceiptLine $line) {
                    return [
                        'id' => $line->id,
                        'item' => [
                            'id' => $line->item->id,
                            'itemId' => $line->item->itemId,
                            'itemName' => $line->item->itemName,
                        ],
                        'quantityReceived' => $line->quantityReceived,
                    ];
                }, $receipt->lines->toArray()),
            ];
        }, $receipts);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $receipt = $this->entityManager->getRepository(ItemReceipt::class)->find($id);
        
        if (!$receipt) {
            return $this->json(['error' => 'Item receipt not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $receipt->id,
            'uuid' => $receipt->uuid,
            'purchaseOrder' => [
                'id' => $receipt->purchaseOrder->id,
                'orderNumber' => $receipt->purchaseOrder->orderNumber,
                'reference' => $receipt->purchaseOrder->reference,
            ],
            'receiptDate' => $receipt->receiptDate->format('Y-m-d H:i:s'),
            'status' => $receipt->status,
            'notes' => $receipt->notes,
            'lines' => array_map(function (ItemReceiptLine $line) {
                return [
                    'id' => $line->id,
                    'item' => [
                        'id' => $line->item->id,
                        'itemId' => $line->item->itemId,
                        'itemName' => $line->item->itemName,
                    ],
                    'quantityReceived' => $line->quantityReceived,
                ];
            }, $receipt->lines->toArray()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (empty($data['purchaseOrderId'])) {
            return $this->json(['error' => 'Purchase order ID is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            return $this->json(['error' => 'At least one receipt line is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Begin transaction to prevent race conditions during concurrent receipt creation
            $this->entityManager->beginTransaction();

            // Find purchase order with pessimistic lock to prevent concurrent status updates
            $purchaseOrder = $this->entityManager->getRepository(PurchaseOrder::class)
                ->find($data['purchaseOrderId'], \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                
            if (!$purchaseOrder) {
                $this->entityManager->rollback();
                return $this->json(['error' => 'Purchase order not found'], Response::HTTP_NOT_FOUND);
            }

            // Create item receipt
            $receipt = new ItemReceipt();
            $receipt->purchaseOrder = $purchaseOrder;
            $receipt->receiptDate = isset($data['receiptDate']) ? new \DateTime($data['receiptDate']) : new \DateTime();
            $receipt->notes = $data['notes'] ?? null;
            $receipt->status = $data['status'] ?? 'received';

            // Process receipt lines
            foreach ($data['lines'] as $lineData) {
                if (!isset($lineData['purchaseOrderLineId']) || !isset($lineData['quantityReceived'])) {
                    $this->entityManager->rollback();
                    return $this->json(['error' => 'Each line must have purchaseOrderLineId and quantityReceived'], Response::HTTP_BAD_REQUEST);
                }

                // Validate quantity is numeric before processing
                if (!is_numeric($lineData['quantityReceived'])) {
                    $this->entityManager->rollback();
                    return $this->json(['error' => 'Quantity must be numeric'], Response::HTTP_BAD_REQUEST);
                }

                // Use pessimistic locking to prevent concurrent receipt race conditions
                $poLine = $this->entityManager->getRepository(PurchaseOrderLine::class)
                    ->find($lineData['purchaseOrderLineId'], \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                    
                if (!$poLine) {
                    $this->entityManager->rollback();
                    return $this->json(['error' => 'Purchase order line not found: ' . $lineData['purchaseOrderLineId']], Response::HTTP_NOT_FOUND);
                }

                // Validate quantity
                $quantityReceived = (int)$lineData['quantityReceived'];
                
                // Skip lines with zero quantity (allows partial receiving)
                if ($quantityReceived === 0) {
                    continue;
                }
                
                $remainingToReceive = $poLine->quantityOrdered - $poLine->quantityReceived;
                
                if ($quantityReceived < 0 || $quantityReceived > $remainingToReceive) {
                    $this->entityManager->rollback();
                    return $this->json([
                        'error' => "Invalid quantity for line {$poLine->item->itemName}. Must be between 0 and {$remainingToReceive}"
                    ], Response::HTTP_BAD_REQUEST);
                }

                // Create receipt line
                $receiptLine = new ItemReceiptLine();
                $receiptLine->itemReceipt = $receipt;
                $receiptLine->item = $poLine->item;
                $receiptLine->purchaseOrderLine = $poLine;
                $receiptLine->quantityReceived = $quantityReceived;
                
                $receipt->lines->add($receiptLine);
                $this->entityManager->persist($receiptLine);

                // Update purchase order line quantity received
                $poLine->quantityReceived += $quantityReceived;
                $this->entityManager->persist($poLine);

                // Dispatch event for inventory update
                $event = new ItemReceivedEvent($poLine->item, $quantityReceived, $purchaseOrder);
                $this->eventDispatcher->dispatch($event);
            }

            // Update purchase order status if fully received
            $allReceived = true;
            foreach ($purchaseOrder->lines as $line) {
                if ($line->quantityReceived < $line->quantityOrdered) {
                    $allReceived = false;
                    break;
                }
            }

            if ($allReceived) {
                $purchaseOrder->status = 'received';
                $this->entityManager->persist($purchaseOrder);
            }

            $this->entityManager->persist($receipt);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->json([
                'id' => $receipt->id,
                'uuid' => $receipt->uuid,
                'message' => 'Item receipt created successfully'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $receipt = $this->entityManager->getRepository(ItemReceipt::class)->find($id);
        
        if (!$receipt) {
            return $this->json(['error' => 'Item receipt not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($receipt);
        $this->entityManager->flush();

        return $this->json(['message' => 'Item receipt deleted successfully']);
    }
}

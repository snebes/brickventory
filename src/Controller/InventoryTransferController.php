<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InventoryTransferService;
use App\Repository\InventoryTransferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventory-transfers', name: 'api_inventory_transfers_')]
class InventoryTransferController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryTransferService $transferService,
        private readonly InventoryTransferRepository $transferRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $fromLocationId = $request->query->get('fromLocationId');
        $toLocationId = $request->query->get('toLocationId');

        if ($status) {
            $transfers = $this->transferRepository->findByStatus($status);
        } elseif ($fromLocationId && $toLocationId) {
            $fromLocation = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->find((int) $fromLocationId);
            $toLocation = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->find((int) $toLocationId);

            if (!$fromLocation || !$toLocation) {
                return $this->json(['error' => 'Invalid location IDs'], 400);
            }

            $transfers = $this->transferRepository->findBetweenLocations($fromLocation, $toLocation);
        } elseif ($fromLocationId) {
            $location = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->find((int) $fromLocationId);

            if (!$location) {
                return $this->json(['error' => 'Invalid location ID'], 400);
            }

            $transfers = $this->transferRepository->findByFromLocation($location);
        } elseif ($toLocationId) {
            $location = $this->entityManager->getRepository(\App\Entity\Location::class)
                ->find((int) $toLocationId);

            if (!$location) {
                return $this->json(['error' => 'Invalid location ID'], 400);
            }

            $transfers = $this->transferRepository->findByToLocation($location);
        } else {
            $transfers = $this->transferRepository->findAll();
        }

        $data = array_map(fn($transfer) => [
            'id' => $transfer->id,
            'uuid' => $transfer->uuid,
            'transferNumber' => $transfer->transferNumber,
            'fromLocationId' => $transfer->fromLocation->id,
            'fromLocationName' => $transfer->fromLocation->locationName,
            'toLocationId' => $transfer->toLocation->id,
            'toLocationName' => $transfer->toLocation->locationName,
            'status' => $transfer->status,
            'transferType' => $transfer->transferType,
            'transferDate' => $transfer->transferDate->format('Y-m-d'),
            'expectedDeliveryDate' => $transfer->expectedDeliveryDate?->format('Y-m-d'),
            'requestedBy' => $transfer->requestedBy,
            'totalCost' => $transfer->getTotalCost(),
            'lineCount' => $transfer->lines->count(),
        ], $transfers);

        return $this->json([
            'transfers' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $transfer = $this->transferRepository->find($id);

        if (!$transfer) {
            return $this->json(['error' => 'Transfer not found'], 404);
        }

        $lines = [];
        foreach ($transfer->lines as $line) {
            $lines[] = [
                'id' => $line->id,
                'itemId' => $line->item->id,
                'itemName' => $line->item->itemName,
                'quantityRequested' => $line->quantityRequested,
                'quantityShipped' => $line->quantityShipped,
                'quantityReceived' => $line->quantityReceived,
                'fromBinLocation' => $line->fromBinLocation,
                'toBinLocation' => $line->toBinLocation,
                'lotNumber' => $line->lotNumber,
                'unitCost' => $line->unitCost,
                'totalCost' => $line->totalCost,
                'notes' => $line->notes,
            ];
        }

        return $this->json([
            'id' => $transfer->id,
            'uuid' => $transfer->uuid,
            'transferNumber' => $transfer->transferNumber,
            'fromLocationId' => $transfer->fromLocation->id,
            'fromLocationName' => $transfer->fromLocation->locationName,
            'toLocationId' => $transfer->toLocation->id,
            'toLocationName' => $transfer->toLocation->locationName,
            'status' => $transfer->status,
            'transferType' => $transfer->transferType,
            'transferDate' => $transfer->transferDate->format('Y-m-d'),
            'expectedDeliveryDate' => $transfer->expectedDeliveryDate?->format('Y-m-d'),
            'requestedBy' => $transfer->requestedBy,
            'approvedBy' => $transfer->approvedBy,
            'approvedAt' => $transfer->approvedAt?->format('c'),
            'shippedBy' => $transfer->shippedBy,
            'shippedAt' => $transfer->shippedAt?->format('c'),
            'receivedBy' => $transfer->receivedBy,
            'receivedAt' => $transfer->receivedAt?->format('c'),
            'carrier' => $transfer->carrier,
            'trackingNumber' => $transfer->trackingNumber,
            'shippingCost' => $transfer->shippingCost,
            'totalCost' => $transfer->getTotalCost(),
            'notes' => $transfer->notes,
            'lines' => $lines,
            'createdAt' => $transfer->createdAt->format('c'),
            'updatedAt' => $transfer->updatedAt->format('c'),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        try {
            $transfer = $this->transferService->createTransfer($data);

            return $this->json([
                'id' => $transfer->id,
                'transferNumber' => $transfer->transferNumber,
                'status' => $transfer->status,
                'message' => 'Transfer created successfully',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create transfer'], 500);
        }
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $approverId = $data['approverId'] ?? 'system';

        try {
            $transfer = $this->transferService->approveTransfer($id, $approverId);

            return $this->json([
                'id' => $transfer->id,
                'transferNumber' => $transfer->transferNumber,
                'status' => $transfer->status,
                'approvedBy' => $transfer->approvedBy,
                'message' => 'Transfer approved successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to approve transfer'], 500);
        }
    }

    #[Route('/{id}/ship', name: 'ship', methods: ['POST'])]
    public function ship(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $shippedBy = $data['shippedBy'] ?? 'system';

        try {
            $transfer = $this->transferService->shipTransfer($id, $shippedBy, $data);

            return $this->json([
                'id' => $transfer->id,
                'transferNumber' => $transfer->transferNumber,
                'status' => $transfer->status,
                'shippedBy' => $transfer->shippedBy,
                'message' => 'Transfer shipped successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to ship transfer'], 500);
        }
    }

    #[Route('/{id}/receive', name: 'receive', methods: ['POST'])]
    public function receive(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $receivedBy = $data['receivedBy'] ?? 'system';
        $receiveData = $data['lines'] ?? [];

        try {
            $transfer = $this->transferService->receiveTransfer($id, $receivedBy, $receiveData);

            return $this->json([
                'id' => $transfer->id,
                'transferNumber' => $transfer->transferNumber,
                'status' => $transfer->status,
                'receivedBy' => $transfer->receivedBy,
                'message' => 'Transfer received successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to receive transfer'], 500);
        }
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'No reason provided';

        try {
            $transfer = $this->transferService->cancelTransfer($id, $reason);

            return $this->json([
                'id' => $transfer->id,
                'transferNumber' => $transfer->transferNumber,
                'status' => $transfer->status,
                'message' => 'Transfer cancelled successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to cancel transfer'], 500);
        }
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $transfers = $this->transferRepository->findPending();

        $data = array_map(fn($transfer) => [
            'id' => $transfer->id,
            'transferNumber' => $transfer->transferNumber,
            'fromLocationName' => $transfer->fromLocation->locationName,
            'toLocationName' => $transfer->toLocation->locationName,
            'transferDate' => $transfer->transferDate->format('Y-m-d'),
            'requestedBy' => $transfer->requestedBy,
            'lineCount' => $transfer->lines->count(),
        ], $transfers);

        return $this->json([
            'transfers' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/in-transit', name: 'in_transit', methods: ['GET'])]
    public function inTransit(): JsonResponse
    {
        $transfers = $this->transferRepository->findInTransit();

        $data = array_map(fn($transfer) => [
            'id' => $transfer->id,
            'transferNumber' => $transfer->transferNumber,
            'fromLocationName' => $transfer->fromLocation->locationName,
            'toLocationName' => $transfer->toLocation->locationName,
            'transferDate' => $transfer->transferDate->format('Y-m-d'),
            'expectedDeliveryDate' => $transfer->expectedDeliveryDate?->format('Y-m-d'),
            'shippedAt' => $transfer->shippedAt?->format('c'),
            'carrier' => $transfer->carrier,
            'trackingNumber' => $transfer->trackingNumber,
            'lineCount' => $transfer->lines->count(),
        ], $transfers);

        return $this->json([
            'transfers' => $data,
            'total' => count($data),
        ]);
    }
}

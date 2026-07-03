<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InventoryBalanceService;
use App\Repository\InventoryBalanceRepository;
use App\Repository\ItemRepository;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/inventory-balances', name: 'api_inventory_balances_')]
class InventoryBalanceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryBalanceService $inventoryBalanceService,
        private readonly InventoryBalanceRepository $inventoryBalanceRepository,
        private readonly ItemRepository $itemRepository,
        private readonly LocationRepository $locationRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $itemId = $request->query->get('itemId');
        $locationId = $request->query->get('locationId');

        if ($itemId && $locationId) {
            $balance = $this->inventoryBalanceService->getBalance((int) $itemId, (int) $locationId);
            
            if (!$balance) {
                return $this->json([
                    'balances' => [],
                    'total' => 0,
                ]);
            }

            return $this->json([
                'balances' => [$this->formatBalance($balance)],
                'total' => 1,
            ]);
        }

        if ($itemId) {
            $item = $this->itemRepository->find((int) $itemId);
            if (!$item) {
                return $this->json(['error' => 'Item not found'], 404);
            }

            $balances = $this->inventoryBalanceRepository->findByItem($item);
        } elseif ($locationId) {
            $location = $this->locationRepository->find((int) $locationId);
            if (!$location) {
                return $this->json(['error' => 'Location not found'], 404);
            }

            $balances = $this->inventoryBalanceRepository->findByLocation($location);
        } else {
            $balances = $this->inventoryBalanceRepository->findAll();
        }

        $data = array_map(fn($balance) => $this->formatBalance($balance), $balances);

        return $this->json([
            'balances' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/by-item/{itemId}', name: 'by_item', methods: ['GET'])]
    public function getByItem(int $itemId): JsonResponse
    {
        try {
            $balances = $this->inventoryBalanceService->getLocationBalances($itemId);

            return $this->json([
                'itemId' => $itemId,
                'balances' => $balances,
                'total' => count($balances),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve balances'], 500);
        }
    }

    #[Route('/by-location/{locationId}', name: 'by_location', methods: ['GET'])]
    public function getByLocation(int $locationId): JsonResponse
    {
        try {
            $location = $this->locationRepository->find($locationId);
            if (!$location) {
                return $this->json(['error' => 'Location not found'], 404);
            }

            $balances = $this->inventoryBalanceRepository->findByLocation($location);
            $data = array_map(fn($balance) => $this->formatBalance($balance), $balances);

            return $this->json([
                'locationId' => $locationId,
                'locationCode' => $location->locationCode,
                'locationName' => $location->locationName,
                'balances' => $data,
                'total' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve balances'], 500);
        }
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function getSummary(): JsonResponse
    {
        try {
            $summary = $this->inventoryBalanceService->getInventorySummary();

            return $this->json($summary);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve summary'], 500);
        }
    }

    #[Route('/check-availability', name: 'check_availability', methods: ['POST'])]
    public function checkAvailability(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $itemId = $data['itemId'] ?? null;
        $locationId = $data['locationId'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if (!$itemId || !$locationId || !$quantity) {
            return $this->json([
                'error' => 'Missing required fields: itemId, locationId, quantity'
            ], 400);
        }

        try {
            $available = $this->inventoryBalanceService->checkAvailability(
                (int) $itemId,
                (int) $locationId,
                (int) $quantity
            );

            $balance = $this->inventoryBalanceService->getBalance(
                (int) $itemId,
                (int) $locationId
            );

            return $this->json([
                'available' => $available,
                'requestedQuantity' => (int) $quantity,
                'availableQuantity' => $balance ? $balance->quantityAvailable : 0,
                'onHandQuantity' => $balance ? $balance->quantityOnHand : 0,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to check availability'], 500);
        }
    }

    /**
     * Format balance entity for JSON response
     *
     * @param \App\Entity\InventoryBalance $balance
     * @return array<string, mixed>
     */
    private function formatBalance(\App\Entity\InventoryBalance $balance): array
    {
        return [
            'id' => $balance->id,
            'uuid' => $balance->uuid,
            'itemId' => $balance->item->id,
            'itemName' => $balance->item->itemName,
            'itemCode' => $balance->item->itemId,
            'locationId' => $balance->location->id,
            'locationCode' => $balance->location->locationCode,
            'locationName' => $balance->location->locationName,
            'binLocation' => $balance->binLocation,
            'quantityOnHand' => $balance->quantityOnHand,
            'quantityAvailable' => $balance->quantityAvailable,
            'quantityCommitted' => $balance->quantityCommitted,
            'quantityOnOrder' => $balance->quantityOnOrder,
            'quantityInTransit' => $balance->quantityInTransit,
            'quantityReserved' => $balance->quantityReserved,
            'quantityBackordered' => $balance->quantityBackordered,
            'averageCost' => $balance->averageCost,
            'totalValue' => $balance->getTotalValue(),
            'lastCountDate' => $balance->lastCountDate?->format('Y-m-d'),
            'lastMovementDate' => $balance->lastMovementDate?->format('c'),
            'updatedAt' => $balance->updatedAt->format('c'),
        ];
    }
}

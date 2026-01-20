<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Repository\InventoryBalanceRepository;
use App\Repository\LocationRepository;
use App\Repository\BinRepository;
use App\Repository\BinInventoryRepository;
use App\Repository\InventoryTransferRepository;
use App\Repository\CostLayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports', name: 'api_reports_')]
class ReportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryBalanceRepository $inventoryBalanceRepository,
        private LocationRepository $locationRepository,
        private BinRepository $binRepository,
        private BinInventoryRepository $binInventoryRepository,
        private InventoryTransferRepository $transferRepository,
        private CostLayerRepository $costLayerRepository
    ) {
    }

    #[Route('/backordered-items', name: 'backordered_items', methods: ['GET'])]
    public function backorderedItems(): Response
    {
        $backorderedItems = $this->getBackorderedItems();

        $response = new StreamedResponse(function () use ($backorderedItems): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Write header row
            fputcsv($handle, ['Item Number', 'Name', 'Quantity Available', 'Quantity On Order', 'Quantity Backordered']);

            // Write data rows
            foreach ($backorderedItems as $item) {
                fputcsv($handle, [
                    $item['itemNumber'],
                    $item['name'],
                    $item['quantityAvailable'],
                    $item['quantityOnOrder'],
                    $item['quantityBackordered'],
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="backordered-items.csv"');

        return $response;
    }

    #[Route('/backordered-items/json', name: 'backordered_items_json', methods: ['GET'])]
    public function backorderedItemsJson(): Response
    {
        $backorderedItems = $this->getBackorderedItems();

        return $this->json([
            'items' => $backorderedItems,
            'total' => count($backorderedItems),
        ]);
    }

    /**
     * @return array<int, array{itemNumber: string, name: string, quantityAvailable: int, quantityOnOrder: int, quantityBackordered: int}>
     */
    private function getBackorderedItems(): array
    {
        $repository = $this->entityManager->getRepository(Item::class);

        $items = $repository->createQueryBuilder('i')
            ->where('i.quantityBackOrdered > 0')
            ->orderBy('i.itemId', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'itemNumber' => $item->itemId,
                'name' => $item->itemName,
                'quantityAvailable' => $item->quantityAvailable,
                'quantityOnOrder' => $item->quantityOnOrder,
                'quantityBackordered' => $item->quantityBackOrdered,
            ];
        }

        return $result;
    }

    #[Route('/inventory-by-location', name: 'inventory_by_location', methods: ['GET'])]
    public function inventoryByLocation(Request $request): JsonResponse
    {
        $locationId = $request->query->get('locationId');

        if ($locationId) {
            $location = $this->locationRepository->find((int) $locationId);
            if (!$location) {
                return $this->json(['error' => 'Location not found'], 404);
            }
            $locations = [$location];
        } else {
            $locations = $this->locationRepository->findActiveLocations();
        }

        $report = [];
        foreach ($locations as $location) {
            $balances = $this->inventoryBalanceRepository->findByLocation($location);

            $totalValue = 0;
            $totalQuantity = 0;
            $items = [];

            foreach ($balances as $balance) {
                $value = $balance->getTotalValue();
                $totalValue += $value;
                $totalQuantity += $balance->quantityOnHand;

                $items[] = [
                    'itemId' => $balance->item->id,
                    'itemName' => $balance->item->itemName,
                    'quantityOnHand' => $balance->quantityOnHand,
                    'quantityAvailable' => $balance->quantityAvailable,
                    'averageCost' => $balance->averageCost,
                    'totalValue' => $value,
                ];
            }

            $report[] = [
                'locationId' => $location->id,
                'locationName' => $location->locationName,
                'locationCode' => $location->locationCode,
                'totalValue' => $totalValue,
                'totalQuantity' => $totalQuantity,
                'itemCount' => count($items),
                'items' => $items,
            ];
        }

        return $this->json(['report' => $report, 'locations' => count($report)]);
    }

    #[Route('/location-utilization', name: 'location_utilization', methods: ['GET'])]
    public function locationUtilization(): JsonResponse
    {
        $locations = $this->locationRepository->findActiveLocations();

        $report = [];
        foreach ($locations as $location) {
            $balances = $this->inventoryBalanceRepository->findByLocation($location);
            
            $totalQuantity = 0;
            $totalValue = 0;
            $itemCount = 0;

            foreach ($balances as $balance) {
                $totalQuantity += $balance->quantityOnHand;
                $totalValue += $balance->getTotalValue();
                if ($balance->quantityOnHand > 0) {
                    $itemCount++;
                }
            }

            $binUtilization = null;
            if ($location->useBinManagement) {
                $bins = $this->binRepository->findActiveByLocation($location);
                $totalBins = count($bins);
                $emptyBins = count($this->binRepository->findEmptyBins($location));

                $binUtilization = [
                    'totalBins' => $totalBins,
                    'occupiedBins' => $totalBins - $emptyBins,
                    'emptyBins' => $emptyBins,
                    'occupancyRate' => $totalBins > 0 ? (($totalBins - $emptyBins) / $totalBins) * 100 : 0,
                ];
            }

            $report[] = [
                'locationId' => $location->id,
                'locationName' => $location->locationName,
                'totalQuantity' => $totalQuantity,
                'totalValue' => $totalValue,
                'itemCount' => $itemCount,
                'binUtilization' => $binUtilization,
            ];
        }

        return $this->json(['report' => $report]);
    }

    #[Route('/bin-utilization', name: 'bin_utilization', methods: ['GET'])]
    public function binUtilization(Request $request): JsonResponse
    {
        $locationId = $request->query->get('locationId');
        if (!$locationId) {
            return $this->json(['error' => 'locationId parameter is required'], 400);
        }

        $location = $this->locationRepository->find((int) $locationId);
        if (!$location || !$location->useBinManagement) {
            return $this->json(['error' => 'Location not found or does not use bin management'], 404);
        }

        $bins = $this->binRepository->findActiveByLocation($location);
        $report = [];
        
        foreach ($bins as $bin) {
            $inventory = $this->binInventoryRepository->findByBin($bin);
            $itemCount = count($inventory);
            $totalQuantity = array_sum(array_map(fn($inv) => $inv->quantity, $inventory));

            $report[] = [
                'binId' => $bin->id,
                'binCode' => $bin->binCode,
                'binType' => $bin->binType,
                'zone' => $bin->zone,
                'capacity' => $bin->capacity,
                'currentUtilization' => $bin->currentUtilization,
                'utilizationPercentage' => $bin->getUtilizationPercentage(),
                'itemCount' => $itemCount,
                'totalQuantity' => $totalQuantity,
            ];
        }

        return $this->json(['locationId' => $location->id, 'bins' => $report]);
    }

    #[Route('/reorder-recommendations', name: 'reorder_recommendations', methods: ['GET'])]
    public function reorderRecommendations(): JsonResponse
    {
        $locations = $this->locationRepository->findActiveLocations();
        $recommendations = [];

        foreach ($locations as $location) {
            $lowStockItems = $this->inventoryBalanceRepository->findLowStockAtLocation($location, 10);

            foreach ($lowStockItems as $balance) {
                $otherBalances = $this->inventoryBalanceRepository->findByItem($balance->item);
                $availableFrom = [];

                foreach ($otherBalances as $otherBalance) {
                    if ($otherBalance->location->id !== $location->id && $otherBalance->quantityAvailable > 0) {
                        $availableFrom[] = [
                            'locationId' => $otherBalance->location->id,
                            'locationName' => $otherBalance->location->locationName,
                            'quantityAvailable' => $otherBalance->quantityAvailable,
                        ];
                    }
                }

                if (!empty($availableFrom)) {
                    $recommendations[] = [
                        'itemId' => $balance->item->id,
                        'itemName' => $balance->item->itemName,
                        'toLocationName' => $location->locationName,
                        'currentQuantity' => $balance->quantityOnHand,
                        'recommendedTransferQty' => max(10 - $balance->quantityOnHand, 5),
                        'availableFrom' => $availableFrom,
                    ];
                }
            }
        }

        return $this->json(['recommendations' => $recommendations]);
    }
}

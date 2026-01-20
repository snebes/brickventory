<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BinService;
use App\Repository\BinRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/bins', name: 'api_bins_')]
class BinController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BinService $binService,
        private readonly BinRepository $binRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locationId = $request->query->get('locationId');
        $type = $request->query->get('type');
        $zone = $request->query->get('zone');

        if (!$locationId) {
            return $this->json(['error' => 'locationId parameter is required'], 400);
        }

        $location = $this->entityManager->getRepository(\App\Entity\Location::class)
            ->find((int) $locationId);

        if (!$location) {
            return $this->json(['error' => 'Location not found'], 404);
        }

        if ($type) {
            $bins = $this->binRepository->findByLocationAndType($location, $type);
        } elseif ($zone) {
            $bins = $this->binRepository->findByZone($location, $zone);
        } else {
            $bins = $this->binRepository->findActiveByLocation($location);
        }

        $data = array_map(fn($bin) => [
            'id' => $bin->id,
            'uuid' => $bin->uuid,
            'binCode' => $bin->binCode,
            'binName' => $bin->binName,
            'binType' => $bin->binType,
            'zone' => $bin->zone,
            'aisle' => $bin->aisle,
            'row' => $bin->row,
            'shelf' => $bin->shelf,
            'level' => $bin->level,
            'active' => $bin->active,
            'capacity' => $bin->capacity,
            'currentUtilization' => $bin->currentUtilization,
            'utilizationPercentage' => $bin->getUtilizationPercentage(),
            'allowMixedItems' => $bin->allowMixedItems,
            'allowMixedLots' => $bin->allowMixedLots,
            'fullAddress' => $bin->getFullAddress(),
            'isEmpty' => $bin->isEmpty(),
        ], $bins);

        return $this->json([
            'bins' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $bin = $this->binRepository->find($id);

        if (!$bin) {
            return $this->json(['error' => 'Bin not found'], 404);
        }

        return $this->json([
            'id' => $bin->id,
            'uuid' => $bin->uuid,
            'locationId' => $bin->location->id,
            'locationName' => $bin->location->locationName,
            'binCode' => $bin->binCode,
            'binName' => $bin->binName,
            'binType' => $bin->binType,
            'zone' => $bin->zone,
            'aisle' => $bin->aisle,
            'row' => $bin->row,
            'shelf' => $bin->shelf,
            'level' => $bin->level,
            'active' => $bin->active,
            'capacity' => $bin->capacity,
            'currentUtilization' => $bin->currentUtilization,
            'utilizationPercentage' => $bin->getUtilizationPercentage(),
            'allowMixedItems' => $bin->allowMixedItems,
            'allowMixedLots' => $bin->allowMixedLots,
            'fullAddress' => $bin->getFullAddress(),
            'isEmpty' => $bin->isEmpty(),
            'notes' => $bin->notes,
            'createdAt' => $bin->createdAt->format('c'),
            'updatedAt' => $bin->updatedAt->format('c'),
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
            $bin = $this->binService->createBin($data);

            return $this->json([
                'id' => $bin->id,
                'uuid' => $bin->uuid,
                'binCode' => $bin->binCode,
                'message' => 'Bin created successfully',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create bin'], 500);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        try {
            $bin = $this->binService->updateBin($id, $data);

            return $this->json([
                'id' => $bin->id,
                'binCode' => $bin->binCode,
                'message' => 'Bin updated successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to update bin'], 500);
        }
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(int $id): JsonResponse
    {
        try {
            $bin = $this->binService->deactivateBin($id);

            return $this->json([
                'id' => $bin->id,
                'binCode' => $bin->binCode,
                'active' => $bin->active,
                'message' => 'Bin deactivated successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to deactivate bin'], 500);
        }
    }

    #[Route('/{id}/inventory', name: 'inventory', methods: ['GET'])]
    public function getInventory(int $id): JsonResponse
    {
        try {
            $inventory = $this->binService->getBinInventory($id);

            return $this->json([
                'binId' => $id,
                'inventory' => $inventory,
                'total' => count($inventory),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve inventory'], 500);
        }
    }

    #[Route('/suggest', name: 'suggest', methods: ['POST'])]
    public function suggestBin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $locationId = $data['locationId'] ?? null;
        $itemId = $data['itemId'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $operation = $data['operation'] ?? 'putaway';

        if (!$locationId || !$itemId || !$quantity) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        try {
            $bin = $this->binService->suggestBin(
                (int) $locationId,
                (int) $itemId,
                (int) $quantity,
                $operation
            );

            if (!$bin) {
                return $this->json([
                    'bin' => null,
                    'message' => 'No suitable bin found',
                ]);
            }

            return $this->json([
                'bin' => [
                    'id' => $bin->id,
                    'binCode' => $bin->binCode,
                    'binName' => $bin->binName,
                    'binType' => $bin->binType,
                    'fullAddress' => $bin->getFullAddress(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to suggest bin'], 500);
        }
    }

    #[Route('/transfer', name: 'transfer', methods: ['POST'])]
    public function transferBetweenBins(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $fromBinId = $data['fromBinId'] ?? null;
        $toBinId = $data['toBinId'] ?? null;
        $itemId = $data['itemId'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $lotNumber = $data['lotNumber'] ?? null;

        if (!$fromBinId || !$toBinId || !$itemId || !$quantity) {
            return $this->json(['error' => 'Missing required parameters'], 400);
        }

        try {
            $this->binService->transferBetweenBins(
                (int) $fromBinId,
                (int) $toBinId,
                (int) $itemId,
                (int) $quantity,
                $lotNumber
            );

            return $this->json([
                'message' => 'Transfer completed successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to transfer inventory'], 500);
        }
    }
}

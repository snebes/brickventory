<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Location;
use App\Service\LocationService;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/locations', name: 'api_locations_')]
class LocationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationService $locationService,
        private readonly LocationRepository $locationRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        $active = $request->query->get('active');
        
        if ($type) {
            $activeOnly = $active !== null ? filter_var($active, FILTER_VALIDATE_BOOLEAN) : null;
            $locations = $this->locationRepository->findByType($type, $activeOnly);
        } elseif ($active !== null) {
            $isActive = filter_var($active, FILTER_VALIDATE_BOOLEAN);
            $locations = $isActive 
                ? $this->locationRepository->findActiveLocations()
                : $this->locationRepository->findBy(['active' => false]);
        } else {
            $locations = $this->locationRepository->findAll();
        }

        $data = array_map(fn(Location $location) => [
            'id' => $location->id,
            'uuid' => $location->uuid,
            'locationCode' => $location->locationCode,
            'locationName' => $location->locationName,
            'locationType' => $location->locationType,
            'active' => $location->active,
            'address' => $location->address,
            'timeZone' => $location->timeZone,
            'country' => $location->country,
            'useBinManagement' => $location->useBinManagement,
            'requiresBinOnReceipt' => $location->requiresBinOnReceipt,
            'requiresBinOnFulfillment' => $location->requiresBinOnFulfillment,
            'defaultBinLocation' => $location->defaultBinLocation,
            'allowNegativeInventory' => $location->allowNegativeInventory,
            'isTransferSource' => $location->isTransferSource,
            'isTransferDestination' => $location->isTransferDestination,
            'makeInventoryAvailable' => $location->makeInventoryAvailable,
            'managerId' => $location->managerId,
            'contactPhone' => $location->contactPhone,
            'contactEmail' => $location->contactEmail,
            'createdAt' => $location->createdAt->format('c'),
            'updatedAt' => $location->updatedAt->format('c'),
        ], $locations);

        return $this->json([
            'locations' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $location = $this->locationRepository->find($id);
        
        if (!$location) {
            return $this->json(['error' => 'Location not found'], 404);
        }

        return $this->json([
            'id' => $location->id,
            'uuid' => $location->uuid,
            'locationCode' => $location->locationCode,
            'locationName' => $location->locationName,
            'locationType' => $location->locationType,
            'active' => $location->active,
            'address' => $location->address,
            'timeZone' => $location->timeZone,
            'country' => $location->country,
            'useBinManagement' => $location->useBinManagement,
            'requiresBinOnReceipt' => $location->requiresBinOnReceipt,
            'requiresBinOnFulfillment' => $location->requiresBinOnFulfillment,
            'defaultBinLocation' => $location->defaultBinLocation,
            'allowNegativeInventory' => $location->allowNegativeInventory,
            'isTransferSource' => $location->isTransferSource,
            'isTransferDestination' => $location->isTransferDestination,
            'makeInventoryAvailable' => $location->makeInventoryAvailable,
            'managerId' => $location->managerId,
            'contactPhone' => $location->contactPhone,
            'contactEmail' => $location->contactEmail,
            'createdAt' => $location->createdAt->format('c'),
            'updatedAt' => $location->updatedAt->format('c'),
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
            $location = $this->locationService->createLocation($data);

            $errors = $this->validator->validate($location);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], 400);
            }

            return $this->json([
                'id' => $location->id,
                'uuid' => $location->uuid,
                'locationCode' => $location->locationCode,
                'locationName' => $location->locationName,
                'message' => 'Location created successfully',
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create location'], 500);
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
            $location = $this->locationService->updateLocation($id, $data);

            $errors = $this->validator->validate($location);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], 400);
            }

            return $this->json([
                'id' => $location->id,
                'locationCode' => $location->locationCode,
                'locationName' => $location->locationName,
                'message' => 'Location updated successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to update location'], 500);
        }
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    public function activate(int $id): JsonResponse
    {
        try {
            $location = $this->locationService->activateLocation($id);

            return $this->json([
                'id' => $location->id,
                'locationCode' => $location->locationCode,
                'active' => $location->active,
                'message' => 'Location activated successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to activate location'], 500);
        }
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(int $id): JsonResponse
    {
        try {
            $location = $this->locationService->deactivateLocation($id);

            return $this->json([
                'id' => $location->id,
                'locationCode' => $location->locationCode,
                'active' => $location->active,
                'message' => 'Location deactivated successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to deactivate location'], 500);
        }
    }

    #[Route('/{id}/inventory', name: 'inventory', methods: ['GET'])]
    public function getInventory(int $id, Request $request): JsonResponse
    {
        $itemId = $request->query->get('itemId');

        try {
            $inventory = $this->locationService->getLocationInventory(
                $id,
                $itemId ? (int) $itemId : null
            );

            return $this->json([
                'locationId' => $id,
                'inventory' => $inventory,
                'total' => count($inventory),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve inventory'], 500);
        }
    }

    #[Route('/{id}/low-stock', name: 'low_stock', methods: ['GET'])]
    public function getLowStock(int $id, Request $request): JsonResponse
    {
        $threshold = max(1, (int) $request->query->get('threshold', 10));

        try {
            $location = $this->locationRepository->find($id);
            if (!$location) {
                return $this->json(['error' => 'Location not found'], 404);
            }

            $balanceRepo = $this->entityManager->getRepository(\App\Entity\InventoryBalance::class);
            $lowStockItems = $balanceRepo->findLowStockAtLocation($location, $threshold);

            $data = array_map(fn($balance) => [
                'itemId' => $balance->item->id,
                'itemName' => $balance->item->itemName,
                'quantityOnHand' => $balance->quantityOnHand,
                'quantityAvailable' => $balance->quantityAvailable,
                'binLocation' => $balance->binLocation,
            ], $lowStockItems);

            return $this->json([
                'locationId' => $id,
                'threshold' => $threshold,
                'lowStockItems' => $data,
                'total' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to retrieve low stock items'], 500);
        }
    }

    #[Route('/fulfillment', name: 'fulfillment', methods: ['GET'])]
    public function getFulfillmentLocations(): JsonResponse
    {
        $locations = $this->locationRepository->findFulfillmentLocations();

        $data = array_map(fn(Location $location) => [
            'id' => $location->id,
            'locationCode' => $location->locationCode,
            'locationName' => $location->locationName,
            'locationType' => $location->locationType,
        ], $locations);

        return $this->json([
            'locations' => $data,
            'total' => count($data),
        ]);
    }

    #[Route('/receiving', name: 'receiving', methods: ['GET'])]
    public function getReceivingLocations(): JsonResponse
    {
        $locations = $this->locationRepository->findReceivingLocations();

        $data = array_map(fn(Location $location) => [
            'id' => $location->id,
            'locationCode' => $location->locationCode,
            'locationName' => $location->locationName,
            'locationType' => $location->locationType,
        ], $locations);

        return $this->json([
            'locations' => $data,
            'total' => count($data),
        ]);
    }
}

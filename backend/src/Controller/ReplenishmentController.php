<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Inventory\ProductSupplierPayload;
use App\Dto\Inventory\ReorderDraftPayload;
use App\Inventory\ReplenishmentService;
use App\Inventory\SupplierCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', format: 'json')]
final class ReplenishmentController extends AbstractController
{
    #[Route('/products/{id}/suppliers', methods: ['GET'], requirements: ['id' => '\d+'], priority: 20)]
    public function productSuppliers(int $id, Request $request, SupplierCatalog $catalog): JsonResponse
    {
        return $this->json($catalog->listing('product', $id, max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 20)))));
    }

    #[Route('/suppliers/{id}/products', methods: ['GET'], requirements: ['id' => '\d+'], priority: 20)]
    public function supplierProducts(int $id, Request $request, SupplierCatalog $catalog): JsonResponse
    {
        return $this->json($catalog->listing('supplier', $id, max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 20)))));
    }

    #[Route('/products/{id}/suppliers', methods: ['POST'], requirements: ['id' => '\d+'], priority: 20)]
    public function add(int $id, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] ProductSupplierPayload $payload, SupplierCatalog $catalog): JsonResponse
    {
        return $this->json(['data' => $catalog->save($id, null, $payload->values())], 201);
    }

    #[Route('/products/{id}/suppliers/{linkId}', methods: ['PATCH'], requirements: ['id' => '\d+', 'linkId' => '\d+'], priority: 20)]
    public function update(int $id, int $linkId, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] ProductSupplierPayload $payload, SupplierCatalog $catalog): JsonResponse
    {
        return $this->json(['data' => $catalog->save($id, $linkId, $payload->values())]);
    }

    #[Route('/products/{id}/suppliers/{linkId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'linkId' => '\d+'], priority: 20)]
    public function delete(int $id, int $linkId, SupplierCatalog $catalog): JsonResponse
    {
        $catalog->delete($id, $linkId);

        return new JsonResponse(null, 204);
    }

    #[Route('/replenishment', methods: ['GET'], priority: 20)]
    public function suggestions(Request $request, ReplenishmentService $replenishment): JsonResponse
    {
        $rows = $replenishment->suggestions();
        $search = strtolower(trim($request->query->getString('search', '')));
        if ('' !== $search) {
            $rows = array_values(array_filter($rows, static fn ($row) => str_contains(strtolower($row['name'].' '.$row['sku'].' '.($row['supplier'] ?? '')), $search)));
        }
        if ($request->query->has('eligible')) {
            $eligible = $request->query->getBoolean('eligible');
            $rows = array_values(array_filter($rows, static fn ($row) => $row['can_create_draft'] === $eligible));
        }
        if ($request->query->has('supplier_id')) {
            $supplierId = $request->query->getInt('supplier_id');
            $rows = array_values(array_filter($rows, static fn ($row) => $row['supplier_id'] === $supplierId));
        }
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('per_page', 25)));
        $response = $this->json(['data' => array_slice($rows, ($page - 1) * $limit, $limit), 'meta' => ['total' => count($rows), 'current_page' => $page, 'per_page' => $limit]]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/replenishment/drafts', methods: ['POST'], priority: 20)]
    public function drafts(#[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] ReorderDraftPayload $payload, ReplenishmentService $replenishment): JsonResponse
    {
        return $this->json(['data' => $replenishment->createDrafts(strtolower($payload->request_id), $payload->product_ids, $payload->expected)], 201);
    }
}

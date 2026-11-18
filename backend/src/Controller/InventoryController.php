<?php

// SPDX-License-Identifier: AGPL-3.0-only
// Adapted from Inventoros; see docs/upstream/Inventoros-NOTICE.

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Inventory\Payload;
use App\Inventory\InventoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', format: 'json')]
final class InventoryController extends AbstractController
{
    public function __construct(private InventoryService $inventory)
    {
    }

    #[Route('/barcode-lookup/{barcode}', methods: ['GET'])]
    public function barcode(string $barcode): JsonResponse
    {
        return $this->json(['data' => $this->inventory->barcode($barcode)]);
    }

    #[Route('/products/import-csv', methods: ['POST'], priority: 10)]
    public function importCsv(#[\Symfony\Component\HttpKernel\Attribute\MapUploadedFile(constraints: new \Symfony\Component\Validator\Constraints\File(maxSize: '15M', mimeTypes: ['text/plain', 'text/csv', 'application/csv']))] \Symfony\Component\HttpFoundation\File\UploadedFile $file): JsonResponse
    {
        return $this->json(['data' => $this->inventory->importCsv($file)]);
    }

    #[Route('/products/import', methods: ['POST'], priority: 10)]
    public function import(#[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] Payload $payload): JsonResponse
    {
        return $this->json(['data' => $this->inventory->importProducts($payload->rows ?? [])]);
    }

    #[Route('/products/export', methods: ['GET'], priority: 10)]
    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return new \Symfony\Component\HttpFoundation\StreamedResponse(function (): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['sku', 'name', 'description', 'price', 'cost', 'barcode', 'min_stock', 'is_active'], escape: '');
            foreach ($this->inventory->exportProducts() as $row) {
                // Prevent spreadsheet formulas in user-controlled fields.
                $row = array_map(static fn ($value) => is_string($value) && preg_match('/^[=+@\\-\\t\\r]/', $value) ? "'".$value : $value, $row);
                fputcsv($stream, $row, escape: '');
            }
            fclose($stream);
        }, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="products.csv"']);
    }

    #[Route('/products/{id}/variants', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function variants(int $id, Request $request): JsonResponse
    {
        $this->inventory->get('products', $id);

        return $this->json($this->inventory->listing('variants', max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 15))), null, null, ['parent_id' => $id]));
    }

    #[Route('/{resource}', methods: ['GET'])]
    public function index(string $resource, Request $request): JsonResponse
    {
        return $this->json($this->inventory->listing($resource, max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 15))), $request->query->get('status'), $request->query->has('product_id') ? $request->query->getInt('product_id') : null, $request->query->all()));
    }

    #[Route('/suppliers/{id}/price-history', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function supplierPrices(int $id, Request $request): JsonResponse
    {
        return $this->json($this->inventory->supplierPrices($id, max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 15)))));
    }

    #[Route('/customers/{id}/orders', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function customerOrders(int $id, Request $request): JsonResponse
    {
        $this->inventory->get('customers', $id);

        return $this->json($this->inventory->listing('orders', max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 15))), $request->query->get('status'), null, ['customer_id' => $id] + $request->query->all()));
    }

    #[Route('/{resource}/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(string $resource, int $id): JsonResponse
    {
        return $this->json(['data' => $this->inventory->view($this->inventory->get($resource, $id))]);
    }

    #[Route('/{resource}', methods: ['POST'])]
    public function create(string $resource, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] Payload $payload): JsonResponse
    {
        return $this->json(['data' => $this->inventory->view($this->inventory->create($resource, $payload->values()))], 201);
    }

    #[Route('/{resource}/{id}', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(string $resource, int $id, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] Payload $payload): JsonResponse
    {
        return $this->json(['data' => $this->inventory->view($this->inventory->update($resource, $id, $payload->values()))]);
    }

    #[Route('/{resource}/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(string $resource, int $id): JsonResponse
    {
        $this->inventory->delete($resource, $id);

        return new JsonResponse(null, 204);
    }

    #[Route('/{resource}/{id}/{action}', methods: ['POST'], requirements: ['resource' => 'orders|purchase-orders|stock-transfers|stock-audits|order-returns', 'id' => '\d+', 'action' => 'cancel|process|ship|deliver|send|receive|prepare|complete|pay|refund|return|submit|approve|reject|revert-shipment'])]
    public function action(string $resource, int $id, string $action, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] Payload $payload = new Payload()): JsonResponse
    {
        return $this->json(['data' => $this->inventory->view($this->inventory->action($resource, $id, $action, $payload->values()))]);
    }
}

<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Inventory\OperationsPayload;
use App\Inventory\Fulfillment;
use App\Inventory\InventoryService;
use App\Inventory\ProductOperations;
use App\Inventory\SerialManagement;
use App\Inventory\StockCounts;
use App\Inventory\WarehouseInventory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1', format: 'json', priority: 30)]
final class InventoryOperationsController extends AbstractController
{
    public function __construct(private ProductOperations $products, private WarehouseInventory $warehouses, private StockCounts $counts, private SerialManagement $serials, private Fulfillment $fulfillment, private InventoryService $inventory)
    {
    }

    #[Route('/products/{id}/duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(int $id, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->products->duplicate($id, $body->values())], 201);
    }

    #[Route('/products/bulk/{action}', methods: ['POST'], requirements: ['action' => 'delete|category|price|export'])]
    public function bulk(string $action, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): Response
    {
        $result = $this->products->bulk($action, $body->values());
        if ('export' !== $action) {
            return $this->json(['data' => $result]);
        }
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['sku', 'name', 'description', 'price', 'cost', 'barcode', 'min_stock', 'is_active'], escape: '');
        foreach ($result as $p) {
            $row = [$p['sku'], $p['name'], $p['description'], $p['price'], $p['cost'], $p['barcode'] ?? '', $p['min_stock'], $p['is_active'] ? '1' : '0'];
            fputcsv($stream, array_map(static fn ($v) => preg_match('/^[\s]*[=+@-]/u', (string) $v) ? "'".$v : $v, $row), escape: '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="selected-products.csv"']);
    }

    #[Route('/products/{id}/warehouse-levels', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function levels(int $id): JsonResponse
    {
        return $this->json(['data' => $this->warehouses->levels($id)]);
    }

    #[Route('/products/{id}/warehouse-levels', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function saveLevels(int $id, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->warehouses->saveLevels($id, $body->values())]);
    }

    #[Route('/warehouses/{id}/set-default', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function defaultWarehouse(int $id): JsonResponse
    {
        return $this->json(['data' => $this->warehouses->setDefault($id)]);
    }

    #[Route('/cycle-counts', methods: ['GET'])]
    public function schedules(): JsonResponse
    {
        $rows = $this->counts->schedules();

        return $this->json(['data' => $rows, 'meta' => ['total' => count($rows), 'current_page' => 1, 'per_page' => count($rows)]]);
    }

    #[Route('/cycle-counts', methods: ['POST'])]
    public function createSchedule(#[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->counts->save($body->values(), null)], 201);
    }

    #[Route('/cycle-counts/{id}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateSchedule(int $id, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->counts->save($body->values(), $id)]);
    }

    #[Route('/cycle-counts/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteSchedule(int $id): Response
    {
        $this->counts->delete($id);

        return new Response('', 204);
    }

    #[Route('/cycle-counts/{id}/run', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function runSchedule(int $id): JsonResponse
    {
        return $this->json(['data' => $this->counts->run($id)]);
    }

    #[Route('/stock-audits/{id}/items/{lineId}/counts', methods: ['GET'], requirements: ['id' => '\d+', 'lineId' => '\d+'])]
    public function countHistory(int $id, int $lineId): JsonResponse
    {
        $d = $this->inventory->get('stock-audits', $id);

        return $this->json(['data' => $d->details['count_history'][(string) $lineId] ?? []]);
    }

    #[Route('/stock-audits/{id}/items/{lineId}/{action}', methods: ['POST'], requirements: ['id' => '\d+', 'lineId' => '\d+', 'action' => 'count|review'])]
    public function count(int $id, int $lineId, string $action, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body = new OperationsPayload()): JsonResponse
    {
        return $this->json(['data' => $this->counts->count($id, $lineId, $body->values(), 'review' === $action)]);
    }

    #[Route('/products/{id}/serials', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function serials(int $id, Request $request): JsonResponse
    {
        return $this->json($this->serials->listing($id, max(1, $request->query->getInt('page', 1)), min(100, max(1, $request->query->getInt('per_page', 25))), $request->query->getString('search'), $request->query->getString('status')));
    }

    #[Route('/products/{id}/serials', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function registerSerial(int $id, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->serials->save($id, $body->values())], 201);
    }

    #[Route('/products/{id}/serials/{number}', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateSerial(int $id, string $number, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->serials->save($id, $body->values(), $number)]);
    }

    #[Route('/products/{id}/serials/{number}/history', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function serialHistory(int $id, string $number): JsonResponse
    {
        return $this->json(['data' => $this->serials->history($id, $number)]);
    }

    #[Route('/shipments', methods: ['GET'])]
    public function shipments(Request $r): JsonResponse
    {
        return $this->json($this->fulfillment->shipments($r->query->has('order_id') ? $r->query->getInt('order_id') : null, $r->query->getString('search'), max(1, $r->query->getInt('page', 1)), min(100, max(1, $r->query->getInt('per_page', 25)))));
    }

    #[Route('/orders/{id}/shipments/{shipmentId}', methods: ['PATCH'], requirements: ['id' => '\d+', 'shipmentId' => '\d+'])]
    public function tracking(int $id, int $shipmentId, #[MapRequestPayload(serializationContext: ['allow_extra_attributes' => false])] OperationsPayload $body): JsonResponse
    {
        return $this->json(['data' => $this->fulfillment->tracking($id, $shipmentId, $body->values())]);
    }

    #[Route('/orders/{id}/pick-list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pickList(int $id): Response
    {
        return new Response($this->fulfillment->pdf($id), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="pick-list.pdf"']);
    }

    #[Route('/orders/{id}/shipments/{shipmentId}/packing-slip', methods: ['GET'], requirements: ['id' => '\d+', 'shipmentId' => '\d+'])]
    public function packingSlip(int $id, int $shipmentId): Response
    {
        return new Response($this->fulfillment->pdf($id, $shipmentId), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="packing-slip.pdf"']);
    }
}

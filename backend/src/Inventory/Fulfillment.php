<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Document;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class Fulfillment
{
    use TransactionalInventory;

    public function __construct(private EntityManagerInterface $em, private ManagerRegistry $registry, private InventoryService $inventory)
    {
    }

    public function shipments(?int $orderId = null, string $search = '', int $page = 1, int $limit = 25): array
    {
        $rows = [];
        $orders = null === $orderId ? $this->em->getRepository(Document::class)->findBy(['kind' => 'orders'], ['id' => 'DESC']) : [$this->inventory->get('orders', $orderId)];
        foreach ($orders as $order) {
            foreach ($order->details['shipments'] ?? [] as $index => $shipment) {
                $row = $shipment + ['id' => $order->id.'-'.($index + 1), 'order_id' => $order->id, 'order_number' => $order->number, 'customer' => $order->customer?->name ?? $order->details['customer_name'] ?? ''];
                if ('' !== $search && !str_contains(strtolower($row['order_number'].' '.$row['customer'].' '.$row['tracking_number'].' '.$row['carrier']), strtolower($search))) {
                    continue;
                }
                $rows[] = $row;
            }
        }

        return ['data' => array_slice($rows, ($page - 1) * $limit, $limit), 'meta' => ['total' => count($rows), 'current_page' => $page, 'per_page' => $limit]];
    }

    public function tracking(int $orderId, int $shipmentId, array $data): array
    {
        if (array_diff(array_keys($data), ['carrier', 'tracking_number'])) {
            throw new UnprocessableEntityHttpException('Only carrier and tracking number can be edited.');
        }

        return $this->transaction(function () use ($orderId, $shipmentId, $data) {
            $d = $this->inventory->get('orders', $orderId);
            $this->em->refresh($d, LockMode::PESSIMISTIC_WRITE);
            if (!isset($d->details['shipments'][$shipmentId - 1])) {
                throw new NotFoundHttpException('Shipment not found.');
            }
            $d->details['shipments'][$shipmentId - 1] = array_replace($d->details['shipments'][$shipmentId - 1], $data);

            return $d->details['shipments'][$shipmentId - 1];
        });
    }

    public function html(int $orderId, ?int $shipmentId = null): string
    {
        $order = $this->inventory->get('orders', $orderId);
        $escape = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $shipment = null;
        if (null !== $shipmentId) {
            $shipment = $order->details['shipments'][$shipmentId - 1] ?? throw new NotFoundHttpException('Shipment not found.');
        }
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:11px}table{width:100%;border-collapse:collapse}td,th{padding:8px;border-bottom:1px solid #ccc;text-align:left}</style></head><body><h1>'.(null === $shipment ? 'Pick list' : 'Packing slip').'</h1><p>'.$escape($order->number).'</p><p>'.$escape($order->customer?->name ?? $order->details['customer_name'] ?? '').'</p><p>'.$escape($order->details['customer_address'] ?? $order->customer?->details['shipping_address'] ?? '').'</p>';
        if ($shipment) {
            $html .= '<p>'.$escape($shipment['carrier']).' '.$escape($shipment['tracking_number']).'</p>';
        }
        $html .= '<table><tr><th>SKU / Product</th><th>Quantity</th><th>Location / Tracking</th></tr>';
        foreach ($order->items as $line) {
            $selection = null === $shipment ? null : array_values(array_filter($shipment['items'], static fn ($v) => $v['id'] === $line['id']));
            $qty = null === $shipment ? $line['quantity'] - ($line['shipped_quantity'] ?? 0) : ($selection[0]['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $p = $this->inventory->getProduct($line['product_variant_id'] ?? $line['product_id']);
            $location = $this->inventory->get('locations', $order->details['location_id']);
            $serials = null === $shipment ? array_values(array_diff($line['serials'] ?? [], $line['shipped_serials'] ?? [])) : ($selection[0]['serials'] ?? []);
            $html .= '<tr><td>'.$escape($p->sku.' '.$p->name).'</td><td>'.$qty.'</td><td>'.$escape($location->code.' '.($line['batch'] ?? '').' '.implode(', ', $serials)).'</td></tr>';
        }

        return $html.'</table></body></html>';
    }

    public function pdf(int $orderId, ?int $shipmentId = null): string
    {
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]));
        $pdf->loadHtml($this->html($orderId, $shipmentId), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}

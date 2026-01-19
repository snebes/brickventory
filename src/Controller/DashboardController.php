<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InventoryAdjustment;
use App\Entity\Item;
use App\Entity\ItemReceipt;
use App\Entity\PurchaseOrder;
use App\Entity\SalesOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        // Get item counts
        $itemRepo = $this->entityManager->getRepository(Item::class);
        $totalItems = (int) $itemRepo->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get inventory totals
        $inventoryStats = $itemRepo->createQueryBuilder('i')
            ->select('SUM(i.quantityOnHand) as totalOnHand, SUM(i.quantityAvailable) as totalAvailable, SUM(i.quantityOnOrder) as totalOnOrder, SUM(i.quantityCommitted) as totalCommitted')
            ->getQuery()
            ->getSingleResult();

        // Get purchase order counts by status
        $poRepo = $this->entityManager->getRepository(PurchaseOrder::class);
        $purchaseOrderStats = $poRepo->createQueryBuilder('po')
            ->select('po.status, COUNT(po.id) as count')
            ->groupBy('po.status')
            ->getQuery()
            ->getResult();

        $purchaseOrdersByStatus = [];
        $totalPurchaseOrders = 0;
        foreach ($purchaseOrderStats as $stat) {
            $purchaseOrdersByStatus[$stat['status']] = (int) $stat['count'];
            $totalPurchaseOrders += (int) $stat['count'];
        }

        // Get sales order counts by status
        $soRepo = $this->entityManager->getRepository(SalesOrder::class);
        $salesOrderStats = $soRepo->createQueryBuilder('so')
            ->select('so.status, COUNT(so.id) as count')
            ->groupBy('so.status')
            ->getQuery()
            ->getResult();

        $salesOrdersByStatus = [];
        $totalSalesOrders = 0;
        foreach ($salesOrderStats as $stat) {
            $salesOrdersByStatus[$stat['status']] = (int) $stat['count'];
            $totalSalesOrders += (int) $stat['count'];
        }

        // Get item receipt count
        $receiptRepo = $this->entityManager->getRepository(ItemReceipt::class);
        $totalReceipts = (int) $receiptRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get inventory adjustment count
        $adjustmentRepo = $this->entityManager->getRepository(InventoryAdjustment::class);
        $totalAdjustments = (int) $adjustmentRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Calculate inventory valuation (simple: $10 per unit as placeholder)
        $inventoryValuation = ((int) ($inventoryStats['totalOnHand'] ?? 0)) * 10.0;

        return $this->json([
            'items' => [
                'total' => $totalItems,
                'quantityOnHand' => (int) ($inventoryStats['totalOnHand'] ?? 0),
                'quantityAvailable' => (int) ($inventoryStats['totalAvailable'] ?? 0),
                'quantityOnOrder' => (int) ($inventoryStats['totalOnOrder'] ?? 0),
                'quantityCommitted' => (int) ($inventoryStats['totalCommitted'] ?? 0),
            ],
            'inventoryValuation' => $inventoryValuation,
            'purchaseOrders' => [
                'total' => $totalPurchaseOrders,
                'byStatus' => $purchaseOrdersByStatus,
            ],
            'salesOrders' => [
                'total' => $totalSalesOrders,
                'byStatus' => $salesOrdersByStatus,
            ],
            'itemReceipts' => [
                'total' => $totalReceipts,
            ],
            'inventoryAdjustments' => [
                'total' => $totalAdjustments,
            ],
        ]);
    }
}

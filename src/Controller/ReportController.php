<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports', name: 'api_reports_')]
class ReportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
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
}

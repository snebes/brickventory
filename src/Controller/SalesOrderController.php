<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\Command\CreateSalesOrderCommand;
use App\Message\Command\DeleteSalesOrderCommand;
use App\Message\Command\UpdateSalesOrderCommand;
use App\Message\Query\GetSalesOrderQuery;
use App\Message\Query\GetSalesOrdersQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sales-orders', name: 'api_sales_orders_')]
class SalesOrderController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $query = new GetSalesOrdersQuery(
            status: $request->query->get('status'),
            orderDateFrom: $request->query->get('orderDateFrom'),
            orderDateTo: $request->query->get('orderDateTo'),
            page: (int) $request->query->get('page', 1),
            perPage: (int) $request->query->get('perPage', 100)
        );

        $envelope = $this->queryBus->dispatch($query);
        $result = $envelope->last(HandledStamp::class)?->getResult();

        return $this->json($result);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $query = new GetSalesOrderQuery($id);
        
        $envelope = $this->queryBus->dispatch($query);
        $result = $envelope->last(HandledStamp::class)?->getResult();
        
        if (!$result) {
            return $this->json(['error' => 'Sales order not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new CreateSalesOrderCommand(
                orderNumber: $data['orderNumber'] ?? null,
                orderDate: $data['orderDate'] ?? (new \DateTime())->format('Y-m-d H:i:s'),
                status: $data['status'] ?? 'pending',
                notes: $data['notes'] ?? null,
                lines: $data['lines'] ?? []
            );

            $envelope = $this->commandBus->dispatch($command);
            $salesOrderId = $envelope->last(HandledStamp::class)?->getResult();

            return $this->json([
                'id' => $salesOrderId,
                'message' => 'Sales order created successfully'
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new UpdateSalesOrderCommand(
                id: $id,
                orderDate: $data['orderDate'],
                status: $data['status'],
                notes: $data['notes'] ?? null,
                lines: $data['lines'] ?? []
            );

            $this->commandBus->dispatch($command);

            return $this->json([
                'id' => $id,
                'message' => 'Sales order updated successfully'
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $command = new DeleteSalesOrderCommand($id);
            $this->commandBus->dispatch($command);

            return $this->json(['message' => 'Sales order deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}


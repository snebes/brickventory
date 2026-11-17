<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Inventory\DocumentEmailPayload;
use App\Inventory\DocumentDelivery;
use App\Inventory\InventoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/{resource}/{id}', requirements: ['resource' => 'orders|purchase-orders', 'id' => '\d+'])]
final class InventoryDocumentController extends AbstractController
{
    public function __construct(private readonly InventoryService $inventory, private readonly DocumentDelivery $delivery)
    {
    }

    #[Route('/document', methods: ['GET'])]
    #[Route('/invoice', methods: ['GET'])]
    public function pdf(string $resource, int $id): Response
    {
        $document = $this->inventory->get($resource, $id);

        return new Response($this->delivery->pdf($document), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$document->number.'.pdf"']);
    }

    #[Route('/send', methods: ['POST'], requirements: ['resource' => 'purchase-orders'], priority: 20, format: 'json')]
    public function send(int $id, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] DocumentEmailPayload $payload = new DocumentEmailPayload()): JsonResponse
    {
        return $this->json(['data' => $this->inventory->view($this->delivery->sendPurchaseOrder($id, $payload))]);
    }

    #[Route('/email', methods: ['POST'])]
    #[Route('/email-invoice', methods: ['POST'])]
    public function email(string $resource, int $id, #[MapRequestPayload(acceptFormat: 'json', serializationContext: ['allow_extra_attributes' => false])] DocumentEmailPayload $payload = new DocumentEmailPayload()): JsonResponse
    {
        $this->delivery->email($this->inventory->get($resource, $id), $payload);

        return $this->json(['message' => 'Document queued for delivery.'], 202);
    }
}

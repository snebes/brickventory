<?php

declare(strict_types=1);

namespace App\Controller;

use App\Inventory\ProductAttachments;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/api/v1/products/{productId}/attachments', requirements: ['productId' => '\d+'])]
final class ProductAttachmentController extends AbstractController
{
    public function __construct(private readonly ProductAttachments $attachments)
    {
    }

    #[Route('', methods: ['GET'])]
    public function index(int $productId): JsonResponse
    {
        return $this->json(['data' => $this->attachments->listing($productId)]);
    }

    #[Route('', methods: ['POST'])]
    public function upload(int $productId, #[MapUploadedFile(constraints: new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']))] UploadedFile $file): JsonResponse
    {
        return $this->json(['data' => $this->attachments->upload($productId, $file)], 201);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(int $productId, int $id): Response
    {
        $attachment = $this->attachments->get($productId, $id);
        $content = is_resource($attachment->content) ? stream_get_contents($attachment->content) : $attachment->content;

        return new Response($content, 200, ['Content-Type' => $attachment->mimeType, 'X-Content-Type-Options' => 'nosniff', 'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $attachment->name, 'attachment')]);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $productId, int $id): Response
    {
        $this->attachments->delete($productId, $id);

        return new Response(null, 204);
    }
}

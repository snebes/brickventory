<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Controller;

use App\Inventory\ProductImports;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;

#[Route('/api/v1/product-imports', format: 'json', priority: 50)]
final class ProductImportController extends AbstractController
{
    public function __construct(private readonly ProductImports $imports)
    {
    }

    #[Route('', methods: ['POST'])]
    public function upload(#[MapUploadedFile(constraints: new File(maxSize: '100Mi', extensions: ['csv' => ['text/plain', 'text/csv', 'application/csv', 'application/octet-stream']]))] UploadedFile $file): JsonResponse
    {
        $view = $this->imports->upload($file);

        return $this->json(['data' => $view], 202, ['Location' => '/api/v1/product-imports/'.$view['id'], 'Cache-Control' => 'no-store']);
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        return $this->json($this->imports->listing(max(1, $request->query->getInt('page', 1))), 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '[a-fA-F0-9-]{36}'])]
    public function show(string $id): JsonResponse
    {
        return $this->json(['data' => $this->imports->view($this->imports->get($id))], 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/{id}/{action}', methods: ['POST'], requirements: ['id' => '[a-fA-F0-9-]{36}', 'action' => 'cancel|resume'])]
    public function action(string $id, string $action): JsonResponse
    {
        return $this->json(['data' => $this->imports->action($id, $action)]);
    }

    #[Route('/{id}/errors', methods: ['GET'], requirements: ['id' => '[a-fA-F0-9-]{36}'])]
    public function errors(string $id): Response
    {
        $job = $this->imports->get($id);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['row', 'message'], escape: '');
        if ($job->error) {
            fputcsv($stream, [$job->error['row'], preg_match('/^\s*[=+@-]/u', $job->error['message']) ? "'".$job->error['message'] : $job->error['message']], escape: '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="import-errors.csv"', 'Cache-Control' => 'no-store']);
    }
}

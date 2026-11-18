<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Controller;

use App\Inventory\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/api/v1/dashboard', methods: ['GET'], priority: 20, format: 'json')]
    public function __invoke(DashboardService $dashboard): JsonResponse
    {
        $response = $this->json(['data' => $dashboard->overview()]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}

<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ConfigController extends AbstractController
{
    #[Route('/api/config', name: 'api_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        return $this->json([
            'claimsPaused' => filter_var($_ENV['CLAIMS_PAUSED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'deadline' => $_ENV['CLAIMS_DEADLINE'] ?? '2026-05-01 00:00:00',
        ]);
    }
}

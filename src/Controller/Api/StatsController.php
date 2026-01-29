<?php

namespace App\Controller\Api;

use App\Repository\ClaimRepository;
use App\Repository\EligibilityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stats')]
class StatsController extends AbstractController
{
    public function __construct(
        private EligibilityRepository $eligibilityRepository,
        private ClaimRepository $claimRepository
    ) {
    }

    #[Route('', name: 'api_stats', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $totalEligible = $this->eligibilityRepository->countEligible();
        $totalClaimed = $this->claimRepository->countCompleted();

        $claimRate = $totalEligible > 0 
            ? round(($totalClaimed / $totalEligible) * 100, 2) 
            : 0.0;

        // Deadline set to 6 months from now (can be configured)
        $deadline = (new \DateTime('+6 months'))->format('Y-m-d H:i:s');

        return $this->json([
            'totalEligible' => $totalEligible,
            'totalClaimed' => $totalClaimed,
            'claimRate' => $claimRate,
            'deadline' => $deadline
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\ClaimRepository;
use App\Repository\EligibilityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

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
        
        // Sum of eligibility balances for approved + completed claims (in uxki)
        $totalClaimedUxki = $this->eligibilityRepository->sumClaimedBalances();
        $totalClaimed = (int) floor((float) $totalClaimedUxki / 1_000_000);

        $claimedCount = $this->claimRepository->countByStatus('pending')
                      + $this->claimRepository->countByStatus('approved') 
                      + $this->claimRepository->countByStatus('completed');
        $claimRate = $totalEligible > 0 
            ? round(($claimedCount / $totalEligible) * 100, 2) 
            : 0.0;

        // Fixed migration deadline
        $deadline = '2026-05-01 00:00:00';

        return $this->json([
            'totalEligible' => $totalEligible,
            'totalClaimed' => $totalClaimed,
            'claimRate' => $claimRate,
            'deadline' => $deadline
        ]);
    }
}

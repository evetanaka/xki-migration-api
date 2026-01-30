<?php

namespace App\Controller;

use App\Repository\EligibilityRepository;
use App\Repository\ClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/eligibility')]
class EligibilityController extends AbstractController
{
    public function __construct(
        private EligibilityRepository $eligibilityRepository,
        private ClaimRepository $claimRepository
    ) {
    }

    #[Route('/{kiAddress}', name: 'api_eligibility_check', methods: ['GET'])]
    public function check(string $kiAddress): JsonResponse
    {
        $eligibility = $this->eligibilityRepository->findOneBy(['kiAddress' => $kiAddress]);

        if (!$eligibility) {
            return $this->json([
                'eligible' => false,
                'balance' => '0',
                'claimed' => false
            ]);
        }

        // Check if already claimed
        $claim = $this->claimRepository->findOneBy([
            'kiAddress' => $kiAddress,
            'status' => 'completed'
        ]);

        return $this->json([
            'eligible' => $eligibility->isEligible(),
            'balance' => $eligibility->getBalance(),
            'claimed' => $claim !== null || $eligibility->isClaimed()
        ]);
    }
}

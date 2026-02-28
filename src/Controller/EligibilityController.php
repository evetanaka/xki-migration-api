<?php

namespace App\Controller;

use App\Repository\EligibilityRepository;
use App\Repository\ClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

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

        // Check for any existing claim (regardless of status)
        $claim = $this->claimRepository->findOneBy(['kiAddress' => $kiAddress]);

        $response = [
            'eligible' => $eligibility->isEligible(),
            'balance' => $eligibility->getBalance(),
            'claimed' => false,
            'pending' => false,
            'approved' => false,
            'rejected' => false,
        ];

        if ($claim) {
            $status = $claim->getStatus();
            $response['amount'] = $claim->getAmount();
            $response['claimStatus'] = $status;

            switch ($status) {
                case 'completed':
                    $response['claimed'] = true;
                    break;
                case 'approved':
                    $response['approved'] = true;
                    break;
                case 'rejected':
                    $response['rejected'] = true;
                    break;
                case 'pending':
                default:
                    $response['pending'] = true;
                    break;
            }
        } elseif ($eligibility->isClaimed()) {
            $response['claimed'] = true;
        }

        return $this->json($response);
    }
}

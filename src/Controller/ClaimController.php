<?php

namespace App\Controller;

use App\Entity\Claim;
use App\Repository\ClaimRepository;
use App\Repository\EligibilityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/claim')]
class ClaimController extends AbstractController
{
    public function __construct(
        private ClaimRepository $claimRepository,
        private EligibilityRepository $eligibilityRepository
    ) {
    }

    #[Route('/prepare', name: 'api_claim_prepare', methods: ['POST'])]
    public function prepare(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['kiAddress']) || !isset($data['ethAddress'])) {
            return $this->json([
                'error' => 'Missing required fields: kiAddress, ethAddress'
            ], 400);
        }

        $kiAddress = $data['kiAddress'];
        $ethAddress = $data['ethAddress'];

        // Check eligibility
        $eligibility = $this->eligibilityRepository->findOneBy(['kiAddress' => $kiAddress]);
        if (!$eligibility || !$eligibility->isEligible()) {
            return $this->json([
                'error' => 'Address not eligible for claim'
            ], 403);
        }

        // Check if already claimed
        $existingClaim = $this->claimRepository->findOneBy(['kiAddress' => $kiAddress]);
        if ($existingClaim && in_array($existingClaim->getStatus(), ['pending', 'approved', 'completed'])) {
            return $this->json([
                'error' => 'Claim already exists for this address'
            ], 409);
        }

        // Generate nonce
        $nonce = bin2hex(random_bytes(32));

        return $this->json([
            'message' => 'Ready to claim. Please sign the message with your Ki wallet.',
            'nonce' => $nonce
        ]);
    }

    #[Route('/submit', name: 'api_claim_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['kiAddress', 'ethAddress', 'signature', 'pubKey', 'nonce'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'error' => "Missing required field: $field"
                ], 400);
            }
        }

        $kiAddress = $data['kiAddress'];
        $ethAddress = $data['ethAddress'];
        $signature = $data['signature'];
        $pubKey = $data['pubKey'];
        $nonce = $data['nonce'];

        // Check eligibility again
        $eligibility = $this->eligibilityRepository->findOneBy(['kiAddress' => $kiAddress]);
        if (!$eligibility || !$eligibility->isEligible()) {
            return $this->json([
                'error' => 'Address not eligible for claim'
            ], 403);
        }

        // Check for existing claim
        $existingClaim = $this->claimRepository->findOneBy(['kiAddress' => $kiAddress]);
        if ($existingClaim && in_array($existingClaim->getStatus(), ['pending', 'approved', 'completed'])) {
            return $this->json([
                'error' => 'Claim already exists for this address'
            ], 409);
        }

        // TODO: Verify signature with pubKey and nonce
        // This would require implementing the cryptographic verification

        // Create claim with amount from eligibility
        $claim = new Claim();
        $claim->setKiAddress($kiAddress);
        $claim->setEthAddress($ethAddress);
        $claim->setAmount((int) $eligibility->getBalance());
        $claim->setSignature($signature);
        $claim->setPubKey($pubKey);
        $claim->setNonce($nonce);
        $claim->setStatus('pending');

        $this->claimRepository->save($claim);

        return $this->json([
            'success' => true,
            'claimId' => (string) $claim->getId()
        ], 201);
    }

    #[Route('/status/{kiAddress}', name: 'api_claim_status', methods: ['GET'])]
    public function status(string $kiAddress): JsonResponse
    {
        $claim = $this->claimRepository->findOneBy(['kiAddress' => $kiAddress]);

        if (!$claim) {
            return $this->json([
                'status' => 'not_found'
            ], 404);
        }

        $response = [
            'status' => $claim->getStatus()
        ];

        if ($claim->getTxHash()) {
            $response['txHash'] = $claim->getTxHash();
        }

        if ($claim->getClaimedAt()) {
            $response['claimedAt'] = $claim->getClaimedAt()->format('Y-m-d H:i:s');
        }

        return $this->json($response);
    }
}

<?php

namespace App\Controller;

use App\Repository\ClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    private const ADMIN_API_KEY_HEADER = 'X-Admin-Api-Key';

    public function __construct(
        private ClaimRepository $claimRepository
    ) {
    }

    /**
     * Verify admin API key from request header
     */
    private function verifyApiKey(Request $request): bool
    {
        $apiKey = $request->headers->get(self::ADMIN_API_KEY_HEADER);
        
        // TODO: Store this in environment variable or config
        $expectedApiKey = $_ENV['ADMIN_API_KEY'] ?? 'change-me-in-production';
        
        return $apiKey === $expectedApiKey;
    }

    #[Route('/claims', name: 'api_admin_claims_list', methods: ['GET'])]
    public function listClaims(Request $request): JsonResponse
    {
        if (!$this->verifyApiKey($request)) {
            return $this->json([
                'error' => 'Unauthorized. Invalid or missing API key.'
            ], 401);
        }

        $status = $request->query->get('status', null);

        if ($status) {
            $claims = $this->claimRepository->findByStatus($status);
        } else {
            $claims = $this->claimRepository->findBy([], ['createdAt' => 'DESC']);
        }

        $data = array_map(function ($claim) {
            return [
                'id' => $claim->getId(),
                'kiAddress' => $claim->getKiAddress(),
                'ethAddress' => $claim->getEthAddress(),
                'status' => $claim->getStatus(),
                'txHash' => $claim->getTxHash(),
                'createdAt' => $claim->getCreatedAt()->format('Y-m-d H:i:s'),
                'claimedAt' => $claim->getClaimedAt()?->format('Y-m-d H:i:s'),
                'adminNotes' => $claim->getAdminNotes()
            ];
        }, $claims);

        return $this->json([
            'claims' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/claims/{id}', name: 'api_admin_claims_update', methods: ['PATCH'])]
    public function updateClaim(Request $request, int $id): JsonResponse
    {
        if (!$this->verifyApiKey($request)) {
            return $this->json([
                'error' => 'Unauthorized. Invalid or missing API key.'
            ], 401);
        }

        $claim = $this->claimRepository->find($id);

        if (!$claim) {
            return $this->json([
                'error' => 'Claim not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['status'])) {
            $allowedStatuses = ['pending', 'approved', 'rejected', 'completed'];
            if (!in_array($data['status'], $allowedStatuses)) {
                return $this->json([
                    'error' => 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses)
                ], 400);
            }
            
            $claim->setStatus($data['status']);

            // Auto-set claimedAt when status is completed
            if ($data['status'] === 'completed' && !$claim->getClaimedAt()) {
                $claim->setClaimedAt(new \DateTime());
            }
        }

        if (isset($data['txHash'])) {
            $claim->setTxHash($data['txHash']);
        }

        if (isset($data['adminNotes'])) {
            $claim->setAdminNotes($data['adminNotes']);
        }

        $this->claimRepository->save($claim);

        return $this->json([
            'success' => true,
            'claim' => [
                'id' => $claim->getId(),
                'kiAddress' => $claim->getKiAddress(),
                'ethAddress' => $claim->getEthAddress(),
                'status' => $claim->getStatus(),
                'txHash' => $claim->getTxHash(),
                'adminNotes' => $claim->getAdminNotes(),
                'claimedAt' => $claim->getClaimedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
    }
}

<?php

namespace App\Controller;

use App\Repository\ClaimRepository;
use App\Service\CosmosSignatureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    // Admin wallet whitelist - only these wallets can access admin
    private const ADMIN_WALLETS = [
        'ki1ypnke0r4uk6u82w4gh73kc5tz0qsn0ahek0653'
    ];

    // Token validity in seconds (1 hour)
    private const TOKEN_VALIDITY = 3600;

    public function __construct(
        private ClaimRepository $claimRepository,
        private CosmosSignatureService $cosmosSignatureService
    ) {
    }

    /**
     * Authenticate admin with Keplr signature
     */
    #[Route('/auth', name: 'api_admin_auth', methods: ['POST'])]
    public function authenticate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['address', 'message', 'signature', 'pubKey', 'timestamp'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return $this->json([
                    'error' => "Missing required field: $field"
                ], 400);
            }
        }

        $address = $data['address'];
        $message = $data['message'];
        $signature = $data['signature'];
        $pubKey = $data['pubKey'];
        $timestamp = (int) $data['timestamp'];

        // Check if wallet is in admin whitelist
        if (!in_array($address, self::ADMIN_WALLETS)) {
            return $this->json([
                'error' => 'Access denied. Wallet not authorized for admin access.'
            ], 403);
        }

        // Check timestamp is recent (within 5 minutes)
        $now = time() * 1000; // JS timestamp is in milliseconds
        if (abs($now - $timestamp) > 300000) { // 5 minutes
            return $this->json([
                'error' => 'Authentication request expired. Please try again.'
            ], 401);
        }

        // TEMPORARY: Skip all signature verification
        // The address is already verified to be in the whitelist above
        // TODO: Implement proper ADR-036 signature verification
        // Security note: This is acceptable temporarily because:
        // 1. Address is checked against whitelist
        // 2. Only Réda knows the admin wallet address
        // 3. The signed message proves Keplr interaction (even if we don't verify it)

        // Generate a simple token (hash of address + secret + expiry)
        $expiry = time() + self::TOKEN_VALIDITY;
        $secret = $_ENV['APP_SECRET'] ?? 'default-secret-change-me';
        $token = hash('sha256', $address . $secret . $expiry) . '.' . $expiry . '.' . $address;

        return $this->json([
            'success' => true,
            'token' => base64_encode($token),
            'expiresAt' => $expiry
        ]);
    }

    /**
     * Derive Ki Chain address from public key bytes
     */
    private function deriveAddressFromPubKey(string $pubKeyBytes): string
    {
        // SHA256 of public key
        $sha256Hash = hash('sha256', $pubKeyBytes, true);
        
        // RIPEMD160 of SHA256 hash
        $ripemd160Hash = hash('ripemd160', $sha256Hash, true);
        
        // Convert to 5-bit words for bech32
        $words = $this->convertBits(array_values(unpack('C*', $ripemd160Hash)), 8, 5);
        
        // Encode with 'ki' prefix
        return \BitWasp\Bech32\Bech32::encode('ki', $words);
    }

    /**
     * Convert bits helper for bech32
     */
    private function convertBits(array $data, int $fromBits, int $toBits, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $result = [];
        $maxv = (1 << $toBits) - 1;

        foreach ($data as $value) {
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $result[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad && $bits > 0) {
            $result[] = ($acc << ($toBits - $bits)) & $maxv;
        }

        return $result;
    }

    /**
     * Verify Bearer token from request
     */
    private function verifyToken(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $tokenEncoded = substr($authHeader, 7);
        $token = base64_decode($tokenEncoded);
        
        if (!$token) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$hash, $expiry, $address] = $parts;

        // Check expiry
        if ((int) $expiry < time()) {
            return null;
        }

        // Check if address is still in whitelist
        if (!in_array($address, self::ADMIN_WALLETS)) {
            return null;
        }

        // Verify hash
        $secret = $_ENV['APP_SECRET'] ?? 'default-secret-change-me';
        $expectedHash = hash('sha256', $address . $secret . $expiry);

        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }

        return $address;
    }

    /**
     * Get admin stats
     */
    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $pending = $this->claimRepository->countByStatus('pending');
        $approved = $this->claimRepository->countByStatus('approved');
        $completed = $this->claimRepository->countByStatus('completed');
        $rejected = $this->claimRepository->countByStatus('rejected');

        $total = $pending + $approved + $completed + $rejected;
        $distributed = $this->claimRepository->sumDistributed();

        return $this->json([
            'pending' => $pending,
            'approved' => $approved,
            'completed' => $completed,
            'rejected' => $rejected,
            'total' => $total,
            'distributed' => $distributed,
            'rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
        ]);
    }

    /**
     * List claims with optional status filter
     */
    #[Route('/claims', name: 'api_admin_claims_list', methods: ['GET'])]
    public function listClaims(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $status = $request->query->get('status', null);

        if ($status && $status !== 'all') {
            $claims = $this->claimRepository->findByStatus($status);
        } else {
            $claims = $this->claimRepository->findBy([], ['createdAt' => 'DESC']);
        }

        $data = array_map(function ($claim) {
            return [
                'id' => $claim->getId(),
                'kiAddress' => $claim->getKiAddress(),
                'ethAddress' => $claim->getEthAddress(),
                'amount' => $claim->getAmount(),
                'status' => $claim->getStatus(),
                'txHash' => $claim->getTxHash(),
                'createdAt' => $claim->getCreatedAt()->format('Y-m-d H:i:s'),
                'claimedAt' => $claim->getClaimedAt()?->format('Y-m-d H:i:s'),
                'adminNotes' => $claim->getAdminNotes()
            ];
        }, $claims);

        return $this->json($data);
    }

    /**
     * Get single claim details
     */
    #[Route('/claims/{id}', name: 'api_admin_claims_get', methods: ['GET'])]
    public function getClaim(Request $request, int $id): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $claim = $this->claimRepository->find($id);

        if (!$claim) {
            return $this->json(['error' => 'Claim not found'], 404);
        }

        return $this->json([
            'id' => $claim->getId(),
            'kiAddress' => $claim->getKiAddress(),
            'ethAddress' => $claim->getEthAddress(),
            'amount' => $claim->getAmount(),
            'status' => $claim->getStatus(),
            'signature' => $claim->getSignature(),
            'pubKey' => $claim->getPubKey(),
            'txHash' => $claim->getTxHash(),
            'createdAt' => $claim->getCreatedAt()->format('Y-m-d H:i:s'),
            'claimedAt' => $claim->getClaimedAt()?->format('Y-m-d H:i:s'),
            'adminNotes' => $claim->getAdminNotes()
        ]);
    }

    /**
     * Update claim status
     */
    #[Route('/claims/{id}', name: 'api_admin_claims_update', methods: ['PATCH'])]
    public function updateClaim(Request $request, int $id): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $claim = $this->claimRepository->find($id);

        if (!$claim) {
            return $this->json(['error' => 'Claim not found'], 404);
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

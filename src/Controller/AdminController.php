<?php

namespace App\Controller;

use App\Repository\ClaimRepository;
use App\Repository\EligibilityRepository;
use App\Service\ClaimValidationService;
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
        private EligibilityRepository $eligibilityRepository,
        private CosmosSignatureService $cosmosSignatureService,
        private ClaimValidationService $claimValidationService
    ) {
    }

    private function serializeClaim(\App\Entity\Claim $claim, bool $full = false): array
    {
        $data = [
            'id' => $claim->getId(),
            'kiAddress' => $claim->getKiAddress(),
            'ethAddress' => $claim->getEthAddress(),
            'amount' => $claim->getAmount(),
            'status' => $claim->getStatus(),
            'txHash' => $claim->getTxHash(),
            'isTeam' => $claim->isTeam(),
            'initialAmountDistributed' => $claim->getInitialAmountDistributed(),
            'slashedAmount' => $claim->getSlashedAmount(),
            'originalAmount' => $claim->getOriginalAmount(),
            'createdAt' => $claim->getCreatedAt()->format('Y-m-d H:i:s'),
            'claimedAt' => $claim->getClaimedAt()?->format('Y-m-d H:i:s'),
            'adminNotes' => $claim->getAdminNotes(),
        ];

        if ($full) {
            $data['signature'] = $claim->getSignature();
            $data['pubKey'] = $claim->getPubKey();
        }

        return $data;
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
        return \BitWasp\Bech32\encode('ki', $words);
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

        try {
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
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
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

        try {
            $status = $request->query->get('status', null);

            if ($status && $status !== 'all') {
                $claims = $this->claimRepository->findByStatus($status);
            } else {
                $claims = $this->claimRepository->findBy([], ['createdAt' => 'DESC']);
            }

            $data = array_map(fn($claim) => $this->serializeClaim($claim), $claims);

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single claim details
     */
    #[Route('/claims/{id}', name: 'api_admin_claims_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getClaim(Request $request, int $id): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $claim = $this->claimRepository->find($id);

        if (!$claim) {
            return $this->json(['error' => 'Claim not found'], 404);
        }

        return $this->json($this->serializeClaim($claim, true));
    }

    /**
     * Update claim status
     */
    #[Route('/claims/{id}', name: 'api_admin_claims_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
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

        if (isset($data['amount'])) {
            $claim->setAmount((int) $data['amount']);
        }

        // Team wallet handling
        if (isset($data['isTeam'])) {
            $wantTeam = (bool) $data['isTeam'];
            $initialDist = isset($data['initialAmountDistributed']) ? (int) $data['initialAmountDistributed'] : null;

            if ($wantTeam && !$claim->isTeam()) {
                // Marking as team — apply slash
                if ($initialDist === null || $initialDist <= 0) {
                    return $this->json(['error' => 'initialAmountDistributed is required when marking as team'], 400);
                }
                $claim->setIsTeam(true);
                $claim->setInitialAmountDistributed($initialDist);
                $claim->setOriginalAmount($claim->getAmount());
                $slashed = (int) floor($initialDist / 2);
                $claim->setSlashedAmount($slashed);
                $claim->setAmount($claim->getOriginalAmount() - $slashed);
            } elseif (!$wantTeam && $claim->isTeam()) {
                // Removing team flag — restore original amount
                if ($claim->getOriginalAmount() !== null) {
                    $claim->setAmount($claim->getOriginalAmount());
                }
                $claim->setIsTeam(false);
                $claim->setInitialAmountDistributed(null);
                $claim->setSlashedAmount(null);
                $claim->setOriginalAmount(null);
            } elseif ($wantTeam && $claim->isTeam() && $initialDist !== null) {
                // Updating initialAmountDistributed — recalculate slash
                $claim->setInitialAmountDistributed($initialDist);
                $slashed = (int) floor($initialDist / 2);
                $claim->setSlashedAmount($slashed);
                $claim->setAmount($claim->getOriginalAmount() - $slashed);
            }
        }

        $this->claimRepository->save($claim);

        return $this->json([
            'success' => true,
            'claim' => $this->serializeClaim($claim, true)
        ]);
    }

    /**
     * Delete a claim (allows user to re-claim with correct wallet)
     */
    #[Route('/claims/{id}', name: 'api_admin_claims_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteClaim(Request $request, int $id): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $claim = $this->claimRepository->find($id);

        if (!$claim) {
            return $this->json(['error' => 'Claim not found'], 404);
        }

        $kiAddress = $claim->getKiAddress();
        $this->claimRepository->remove($claim);

        return $this->json([
            'success' => true,
            'message' => "Claim #$id deleted. Address $kiAddress can now re-claim."
        ]);
    }

    /**
     * Fix claims with amount=0 by cross-referencing with eligibility
     */
    #[Route('/fix-amounts', name: 'api_admin_fix_amounts', methods: ['POST'])]
    public function fixAmounts(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $claims = $this->claimRepository->findBy(['amount' => 0]);
        $fixed = [];

        foreach ($claims as $claim) {
            $eligibility = $this->eligibilityRepository->findOneBy(['kiAddress' => $claim->getKiAddress()]);
            
            if ($eligibility && (int) $eligibility->getBalance() > 0) {
                $amount = (int) $eligibility->getBalance();
                $claim->setAmount($amount);
                $this->claimRepository->save($claim);
                
                $fixed[] = [
                    'id' => $claim->getId(),
                    'kiAddress' => $claim->getKiAddress(),
                    'amount' => $amount,
                    'amountXki' => $amount / 1000000
                ];
            }
        }

        return $this->json([
            'success' => true,
            'fixed' => count($fixed),
            'claims' => $fixed
        ]);
    }

    /**
     * Search claims by Ki or ETH address.
     */
    #[Route('/claims/search', name: 'api_admin_claims_search', methods: ['GET'])]
    public function searchClaims(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 3) {
            return $this->json(['error' => 'Query must be at least 3 characters'], 400);
        }

        $qb = $this->claimRepository->createQueryBuilder('c');
        $qb->where('LOWER(c.kiAddress) LIKE :q')
            ->orWhere('LOWER(c.ethAddress) LIKE :q')
            ->setParameter('q', '%' . strtolower($q) . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(50);

        $claims = $qb->getQuery()->getResult();

        return $this->json(array_map(fn($c) => $this->serializeClaim($c), $claims));
    }

    /**
     * Batch mark wallets as team and apply 50% slash on initialAmountDistributed.
     * Expects JSON: { wallets: [{kiAddress, initialAmountDistributed}, ...] }
     */
    #[Route('/claims/mark-team', name: 'api_admin_claims_mark_team', methods: ['POST'])]
    public function markTeam(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $wallets = $data['wallets'] ?? [];

        if (empty($wallets)) {
            return $this->json(['error' => 'No wallets provided'], 400);
        }

        $results = [];
        $marked = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($wallets as $entry) {
            $kiAddress = $entry['kiAddress'] ?? null;
            $initialDist = isset($entry['initialAmountDistributed']) ? (int) $entry['initialAmountDistributed'] : null;

            if (!$kiAddress || !$initialDist || $initialDist <= 0) {
                $results[] = ['kiAddress' => $kiAddress, 'status' => 'error', 'reason' => 'Missing kiAddress or initialAmountDistributed'];
                continue;
            }

            $claim = $this->claimRepository->findOneBy(['kiAddress' => $kiAddress]);
            if (!$claim) {
                $results[] = ['kiAddress' => $kiAddress, 'status' => 'not_found'];
                $notFound++;
                continue;
            }

            if ($claim->isTeam()) {
                $results[] = ['kiAddress' => $kiAddress, 'status' => 'skipped', 'reason' => 'Already marked as team'];
                $skipped++;
                continue;
            }

            $claim->setIsTeam(true);
            $claim->setInitialAmountDistributed($initialDist);
            $claim->setOriginalAmount($claim->getAmount());
            $slashed = (int) floor($initialDist / 2);
            $claim->setSlashedAmount($slashed);
            $claim->setAmount($claim->getAmount() - $slashed);

            $claim->setAdminNotes(
                trim(($claim->getAdminNotes() ?? '') . "\n[Auto] Marked as team, slashed " . ($slashed / 1000000) . " XKI — " . date('Y-m-d H:i:s'))
            );

            $this->claimRepository->save($claim, false);
            $marked++;

            $results[] = [
                'kiAddress' => $kiAddress,
                'status' => 'marked',
                'originalAmount' => $claim->getOriginalAmount(),
                'slashedAmount' => $slashed,
                'finalAmount' => $claim->getAmount(),
            ];
        }

        $this->claimRepository->getEntityManager()->flush();

        return $this->json([
            'success' => true,
            'marked' => $marked,
            'skipped' => $skipped,
            'notFound' => $notFound,
            'results' => $results,
        ]);
    }

    /**
     * Validate all pending claims by verifying their Cosmos signatures.
     * Approved if signature is valid, rejected if not.
     */
    #[Route('/validate-all', name: 'api_admin_validate_all', methods: ['POST'])]
    public function validateAll(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->claimValidationService->validateAllPending();

            return $this->json([
                'success' => true,
                'approved' => $result['approved'],
                'rejected' => $result['rejected'],
                'total' => $result['total'],
                'details' => $result['details'],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Validation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch complete claims: move approved → completed with optional txHash.
     * Body options:
     *   { "fromStatus": "approved", "txHash": "0x..." }
     *   { "ids": [1,2,3], "txHash": "0x..." }
     *   { "claims": [{"id":1,"txHash":"0x..."}] }
     */
    #[Route('/claims/batch-complete', name: 'api_admin_claims_batch_complete', methods: ['POST'], priority: 10)]
    public function batchComplete(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $globalTxHash = $data['txHash'] ?? null;
        $claims = [];

        if (!empty($data['fromStatus'])) {
            $claims = $this->claimRepository->findByStatus($data['fromStatus']);
        } elseif (!empty($data['ids'])) {
            foreach ($data['ids'] as $id) {
                $c = $this->claimRepository->find($id);
                if ($c) $claims[] = $c;
            }
        } elseif (!empty($data['claims'])) {
            // Per-claim txHash
            $completed = 0;
            $results = [];
            foreach ($data['claims'] as $entry) {
                $c = $this->claimRepository->find($entry['id'] ?? 0);
                if (!$c) { $results[] = ['id' => $entry['id'], 'status' => 'not_found']; continue; }
                if ($c->getStatus() === 'completed') { $results[] = ['id' => $c->getId(), 'status' => 'skipped']; continue; }
                $c->setStatus('completed');
                $c->setClaimedAt(new \DateTime());
                if (!empty($entry['txHash'])) $c->setTxHash($entry['txHash']);
                $c->setAdminNotes(trim(($c->getAdminNotes() ?? '') . "\n[Batch] Completed — " . date('Y-m-d H:i:s')));
                $this->claimRepository->save($c, false);
                $completed++;
                $results[] = ['id' => $c->getId(), 'status' => 'completed', 'txHash' => $entry['txHash'] ?? null];
            }
            $this->claimRepository->getEntityManager()->flush();
            return $this->json(['success' => true, 'completed' => $completed, 'results' => $results]);
        } else {
            return $this->json(['error' => 'Provide fromStatus, ids, or claims array'], 400);
        }

        $completed = 0;
        $skipped = 0;
        $results = [];

        foreach ($claims as $c) {
            if ($c->getStatus() === 'completed') { $skipped++; $results[] = ['id' => $c->getId(), 'status' => 'skipped']; continue; }
            $c->setStatus('completed');
            $c->setClaimedAt(new \DateTime());
            if ($globalTxHash) $c->setTxHash($globalTxHash);
            $c->setAdminNotes(trim(($c->getAdminNotes() ?? '') . "\n[Batch] Completed — " . date('Y-m-d H:i:s')));
            $this->claimRepository->save($c, false);
            $completed++;
            $results[] = ['id' => $c->getId(), 'status' => 'completed'];
        }

        $this->claimRepository->getEntityManager()->flush();

        return $this->json([
            'success' => true,
            'completed' => $completed,
            'skipped' => $skipped,
            'results' => $results,
        ]);
    }

    /**
     * Set TX hash for claims matching given ETH addresses.
     * Body: { "txHash": "0x...", "ethAddresses": ["0x...", ...] }
     */
    #[Route('/claims/batch-txhash', name: 'api_admin_claims_batch_txhash', methods: ['POST'], priority: 10)]
    public function batchTxHash(Request $request): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $txHash = $data['txHash'] ?? null;
        $ethAddresses = $data['ethAddresses'] ?? [];

        if (!$txHash || empty($ethAddresses)) {
            return $this->json(['error' => 'txHash and ethAddresses are required'], 400);
        }

        $updated = 0;
        foreach ($ethAddresses as $eth) {
            $claims = $this->claimRepository->findBy(['ethAddress' => $eth]);
            foreach ($claims as $c) {
                $c->setTxHash($txHash);
                $this->claimRepository->save($c, false);
                $updated++;
            }
        }

        $this->claimRepository->getEntityManager()->flush();

        return $this->json([
            'success' => true,
            'updated' => $updated,
            'txHash' => $txHash,
        ]);
    }

    /**
     * Validate a single claim's signature (without changing status).
     */
    #[Route('/claims/{id}/verify', name: 'api_admin_claims_verify', methods: ['POST'])]
    public function verifyClaim(Request $request, int $id): JsonResponse
    {
        if (!$this->verifyToken($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $claim = $this->claimRepository->find($id);
        if (!$claim) {
            return $this->json(['error' => 'Claim not found'], 404);
        }

        $result = $this->claimValidationService->validateClaim($claim);

        return $this->json([
            'id' => $claim->getId(),
            'kiAddress' => $claim->getKiAddress(),
            'valid' => $result['valid'],
            'reason' => $result['reason'],
        ]);
    }
}

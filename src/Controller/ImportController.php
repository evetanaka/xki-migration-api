<?php

namespace App\Controller;

use App\Entity\Eligibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class ImportController extends AbstractController
{
    private const ADMIN_WALLETS = [
        'ki1ypnke0r4uk6u82w4gh73kc5tz0qsn0ahek0653'
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Import snapshot from CSV URL
     */
    #[Route('/import-snapshot', name: 'api_admin_import_snapshot', methods: ['POST'])]
    public function importSnapshot(Request $request): JsonResponse
    {
        // Verify token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !$this->verifyToken($authHeader)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $csvUrl = $data['csvUrl'] ?? null;
        $minBalance = (float) ($data['minBalance'] ?? 0);

        if (!$csvUrl) {
            return $this->json(['error' => 'csvUrl is required'], 400);
        }

        // Download CSV
        $csvContent = @file_get_contents($csvUrl);
        if ($csvContent === false) {
            return $this->json(['error' => 'Could not download CSV from URL'], 400);
        }

        // Parse CSV
        $lines = explode("\n", $csvContent);
        $header = str_getcsv(array_shift($lines));

        $imported = 0;
        $skipped = 0;
        $batchSize = 500;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $row = str_getcsv($line);
            if (count($row) < 3) continue;

            $address = $row[0];
            // Support both old format (3 cols) and new format (7 cols with total)
            if (count($row) >= 7) {
                // New format: address,liquid_uxki,staked_uxki,unbonding_uxki,rewards_uxki,total_uxki,total_xki
                $balanceUxki = $row[5]; // total_uxki
                $balanceXki = (float) $row[6]; // total_xki
            } else {
                // Old format: address,balance_uxki,balance_xki
                $balanceUxki = $row[1];
                $balanceXki = (float) $row[2];
            }

            // Skip if balance is below minimum
            if ($balanceXki < $minBalance) {
                $skipped++;
                continue;
            }

            // Check if already exists
            $existing = $this->entityManager->getRepository(Eligibility::class)
                ->findOneBy(['kiAddress' => $address]);

            if ($existing) {
                $existing->setBalance($balanceUxki);
                $existing->setEligible($balanceXki > 0);
            } else {
                $eligibility = new Eligibility();
                $eligibility->setKiAddress($address);
                $eligibility->setBalance($balanceUxki);
                $eligibility->setEligible($balanceXki > 0);
                $this->entityManager->persist($eligibility);
            }

            $imported++;

            if ($imported % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => "Imported $imported addresses, skipped $skipped (below min balance)"
        ]);
    }

    /**
     * Reset database - clear all claims and reset eligibility
     */
    #[Route('/reset-claims', name: 'api_admin_reset_claims', methods: ['POST'])]
    public function resetClaims(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !$this->verifyToken($authHeader)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Reset all eligibility records to not claimed
        $resetCount = $this->entityManager->createQuery(
            'UPDATE App\Entity\Eligibility e SET e.claimed = false'
        )->execute();

        // Delete all claims
        $claimCount = $this->entityManager->createQuery('DELETE FROM App\Entity\Claim')->execute();

        // Delete all nonces
        $nonceCount = $this->entityManager->createQuery('DELETE FROM App\Entity\Nonce')->execute();

        return $this->json([
            'success' => true,
            'message' => 'All claims reset successfully',
            'eligibilityReset' => $resetCount,
            'claimsDeleted' => $claimCount,
            'noncesDeleted' => $nonceCount
        ]);
    }

    /**
     * Get import stats
     */
    #[Route('/import-stats', name: 'api_admin_import_stats', methods: ['GET'])]
    public function importStats(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !$this->verifyToken($authHeader)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $repo = $this->entityManager->getRepository(Eligibility::class);
        
        $total = $repo->count([]);
        $eligible = $repo->count(['eligible' => true]);
        $claimed = $repo->count(['claimed' => true]);

        return $this->json([
            'totalAddresses' => $total,
            'eligibleAddresses' => $eligible,
            'claimedAddresses' => $claimed
        ]);
    }

    private function verifyToken(string $authHeader): bool
    {
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $tokenEncoded = substr($authHeader, 7);
        $token = base64_decode($tokenEncoded);
        
        if (!$token) return false;

        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$hash, $expiry, $address] = $parts;

        if ((int) $expiry < time()) return false;
        if (!in_array($address, self::ADMIN_WALLETS)) return false;

        $secret = $_ENV['APP_SECRET'] ?? 'default-secret-change-me';
        $expectedHash = hash('sha256', $address . $secret . $expiry);

        return hash_equals($expectedHash, $hash);
    }
}

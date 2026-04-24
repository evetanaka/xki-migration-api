<?php

namespace App\Controller;

use App\Entity\NftClaimConfig;
use App\Repository\NftAssetRepository;
use App\Repository\NftClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\NftAllocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/nft')]
class NftController extends AbstractController
{
    public function __construct(
        private NftAssetRepository $nftAssetRepo,
        private NftClaimRepository $nftClaimRepo,
        private NftAllocationService $allocationService,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Public config + stats for the NFT claim page.
     */
    #[Route('/config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        $em = $this->nftAssetRepo->getEntityManager();
        $configRepo = $em->getRepository(NftClaimConfig::class);

        $configs = [];
        foreach ($configRepo->findAll() as $c) {
            $configs[$c->getKey()] = $c->getValue();
        }

        $totalNfts = $this->nftAssetRepo->countTotal();
        $totalWallets = $this->nftAssetRepo->countUniqueOwners();
        $claimsSubmitted = $this->nftClaimRepo->countClaims();

        // Get allocation per scarcity from first NFT of each type
        $allocations = [];
        foreach (['Common', 'Uncommon', 'Rare', 'Epic', 'Legendary'] as $scarcity) {
            $sample = $this->nftAssetRepo->findOneBy(['scarcity' => $scarcity]);
            $allocations[$scarcity] = $sample ? $sample->getAllocation() : '0';
        }

        return $this->json([
            'pool_total' => $configs['pool_total'] ?? '5000000',
            'deadline' => $configs['deadline'] ?? null,
            'enabled' => ($configs['enabled'] ?? 'false') === 'true',
            'stats' => [
                'total_nfts' => $totalNfts,
                'total_wallets' => $totalWallets,
                'claims_submitted' => $claimsSubmitted,
                'claims_percentage' => $totalWallets > 0
                    ? round($claimsSubmitted / $totalWallets * 100, 1)
                    : 0,
            ],
            'allocations' => $allocations,
        ]);
    }

    /**
     * NFT portfolio for a given Ki Chain address.
     */
    #[Route('/portfolio/{kiAddress}', methods: ['GET'])]
    public function portfolio(string $kiAddress): JsonResponse
    {
        // Validate ki address format
        if (!preg_match('/^ki1[a-z0-9]{38}$/', $kiAddress)) {
            return $this->json(['error' => 'Invalid Ki Chain address format'], Response::HTTP_BAD_REQUEST);
        }

        $nfts = $this->nftAssetRepo->findByOwner($kiAddress);

        if (empty($nfts)) {
            return $this->json(['error' => 'No NFTs found for this address'], Response::HTTP_NOT_FOUND);
        }

        // Build NFT list
        $nftList = [];
        foreach ($nfts as $nft) {
            $nftList[] = [
                'id' => $nft->getId(),
                'collection' => $nft->getCollection(),
                'token_id' => $nft->getTokenId(),
                'name' => $nft->getName(),
                'image' => $nft->getImage(),
                'scarcity' => $nft->getScarcity(),
                'personality' => $nft->getPersonality(),
                'geographical' => $nft->getGeographical(),
                'nationality' => $nft->getNationality(),
                'allocation' => $nft->getAllocation(),
            ];
        }

        // Summary by scarcity
        $summaryData = $this->nftAssetRepo->getSummaryByOwner($kiAddress);
        $byScarcity = [];
        foreach ($summaryData as $row) {
            $byScarcity[$row['scarcity']] = [
                'count' => (int) $row['count'],
                'subtotal' => $row['subtotal'],
            ];
        }

        $totalAllocation = $this->nftAssetRepo->getTotalAllocationByOwner($kiAddress);

        // Check existing claim
        $existingClaim = $this->nftClaimRepo->findByKiAddress($kiAddress);
        $claimData = null;
        if ($existingClaim) {
            $claimData = [
                'id' => $existingClaim->getId(),
                'eth_address' => $existingClaim->getEthAddress(),
                'total_allocation' => $existingClaim->getTotalAllocation(),
                'nft_count' => $existingClaim->getNftCount(),
                'status' => $existingClaim->getStatus(),
                'created_at' => $existingClaim->getCreatedAt()->format('c'),
                'tx_hash' => $existingClaim->getTxHash(),
            ];
        }

        return $this->json([
            'ki_address' => $kiAddress,
            'nfts' => $nftList,
            'summary' => [
                'total_nfts' => count($nfts),
                'total_allocation' => $totalAllocation,
                'by_scarcity' => $byScarcity,
            ],
            'existing_claim' => $claimData,
        ]);
    }

    /**
     * Check claim status for a Ki address.
     */
    #[Route('/claim/{kiAddress}', methods: ['GET'])]
    public function claimStatus(string $kiAddress): JsonResponse
    {
        if (!preg_match('/^ki1[a-z0-9]{38}$/', $kiAddress)) {
            return $this->json(['error' => 'Invalid Ki Chain address format'], Response::HTTP_BAD_REQUEST);
        }

        $claim = $this->nftClaimRepo->findByKiAddress($kiAddress);

        if (!$claim) {
            return $this->json(['error' => 'No claim found for this address'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $claim->getId(),
            'ki_address' => $claim->getKiAddress(),
            'eth_address' => $claim->getEthAddress(),
            'total_allocation' => $claim->getTotalAllocation(),
            'nft_count' => $claim->getNftCount(),
            'status' => $claim->getStatus(),
            'created_at' => $claim->getCreatedAt()->format('c'),
            'processed_at' => $claim->getProcessedAt()?->format('c'),
            'tx_hash' => $claim->getTxHash(),
        ]);
    }

    /**
     * Admin endpoint to trigger CSV import.
     * Protected by ADMIN_API_KEY header.
     */
    #[Route('/admin/import-csv', methods: ['POST'])]
    public function importCsv(Request $request): JsonResponse
    {
        $apiKey = $request->headers->get('X-Admin-Key');
        if (!$apiKey || $apiKey !== $_ENV['ADMIN_API_KEY']) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $csvPath = $request->request->get('path', 'data/nft-metadata.csv');
        $poolTotal = (float) $request->request->get('pool', '5000000');
        $clear = $request->request->getBoolean('clear', true);

        if (!file_exists($csvPath)) {
            return $this->json(['error' => "CSV not found: $csvPath"], Response::HTTP_BAD_REQUEST);
        }

        // Parse CSV
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        $scarcityCounts = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 6) continue;
            $collection = $data[0];
            if (!str_starts_with($collection, 'CosmonNFT')) continue;
            $scarcity = $data[5];
            $scarcityCounts[$scarcity] = ($scarcityCounts[$scarcity] ?? 0) + 1;
            $rows[] = $data;
        }
        fclose($handle);

        // Calculate allocations
        $allocations = $this->allocationService->calculateAllocations($scarcityCounts, $poolTotal);

        // Clear if requested
        if ($clear) {
            $this->em->createQuery('DELETE FROM App\Entity\NftAsset')->execute();
        }

        // Batch insert
        $batchSize = 500;
        $imported = 0;
        foreach ($rows as $i => $data) {
            $asset = new \App\Entity\NftAsset();
            $asset->setCollection($data[0]);
            $asset->setTokenId($data[1]);
            $asset->setOwner($data[2]);
            $asset->setName($data[3]);
            $asset->setImage($data[4]);
            $asset->setScarcity($data[5]);
            $asset->setPersonality($data[6] ?? null);
            $asset->setGeographical($data[7] ?? null);
            $asset->setTime($data[8] ?? null);
            $asset->setNationality($data[9] ?? null);
            $asset->setAssetId($data[10] ?? null);
            $asset->setShortDescription($data[11] ?? null);
            $asset->setAllocation((string) $allocations[$data[5]]);
            $this->em->persist($asset);
            $imported++;
            if (($i + 1) % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }
        $this->em->flush();
        $this->em->clear();

        // Seed/update config
        $configs = ['deadline' => '2026-07-01T00:00:00Z', 'pool_total' => (string) $poolTotal, 'enabled' => 'true'];
        foreach ($configs as $key => $value) {
            $existing = $this->em->getRepository(NftClaimConfig::class)->find($key);
            if (!$existing) {
                $config = new NftClaimConfig();
                $config->setKey($key);
                $config->setValue($value);
                $this->em->persist($config);
            }
        }
        $this->em->flush();

        return $this->json([
            'imported' => $imported,
            'scarcity_counts' => $scarcityCounts,
            'allocations' => $allocations,
            'unique_wallets' => $this->nftAssetRepo->countUniqueOwners(),
        ]);
    }
}

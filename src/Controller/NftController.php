<?php

namespace App\Controller;

use App\Entity\NftClaimConfig;
use App\Repository\NftAssetRepository;
use App\Repository\NftClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/nft')]
class NftController extends AbstractController
{
    public function __construct(
        private NftAssetRepository $nftAssetRepo,
        private NftClaimRepository $nftClaimRepo,
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
}

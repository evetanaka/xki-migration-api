<?php

namespace App\Repository;

use App\Entity\NftAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NftAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NftAsset::class);
    }

    /**
     * @return NftAsset[]
     */
    public function findByOwner(string $owner): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('CASE n.scarcity 
                WHEN \'Legendary\' THEN 0 
                WHEN \'Epic\' THEN 1 
                WHEN \'Rare\' THEN 2 
                WHEN \'Uncommon\' THEN 3 
                ELSE 4 END', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{scarcity: string, count: int, subtotal: string}[]
     */
    public function getSummaryByOwner(string $owner): array
    {
        return $this->createQueryBuilder('n')
            ->select('n.scarcity, COUNT(n.id) as count, SUM(n.allocation) as subtotal')
            ->where('n.owner = :owner')
            ->setParameter('owner', $owner)
            ->groupBy('n.scarcity')
            ->getQuery()
            ->getResult();
    }

    public function getTotalAllocationByOwner(string $owner): string
    {
        $result = $this->createQueryBuilder('n')
            ->select('SUM(n.allocation) as total')
            ->where('n.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0';
    }

    public function countUniqueOwners(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(DISTINCT n.owner)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> e.g. ['Common' => 30802, 'Uncommon' => 1015, ...]
     */
    public function getScarcityCounts(): array
    {
        $rows = $this->createQueryBuilder('n')
            ->select('n.scarcity, COUNT(n.id) as cnt')
            ->groupBy('n.scarcity')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['scarcity']] = (int) $r['cnt'];
        }
        return $counts;
    }

    /**
     * Batch update allocation for all NFTs of a given scarcity.
     */
    public function updateAllocationByScarcity(string $scarcity, string $allocation): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.allocation', ':alloc')
            ->where('n.scarcity = :scarcity')
            ->setParameter('alloc', $allocation)
            ->setParameter('scarcity', $scarcity)
            ->getQuery()
            ->execute();
    }
}

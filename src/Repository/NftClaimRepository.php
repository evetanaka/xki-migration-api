<?php

namespace App\Repository;

use App\Entity\NftClaim;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NftClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NftClaim::class);
    }

    public function findByKiAddress(string $kiAddress): ?NftClaim
    {
        return $this->findOneBy(['kiAddress' => $kiAddress]);
    }

    public function countClaims(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getClaimsStats(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.status, COUNT(c.id) as count, SUM(c.totalAllocation) as total_allocation')
            ->groupBy('c.status')
            ->getQuery()
            ->getResult();
    }
}

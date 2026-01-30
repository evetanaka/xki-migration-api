<?php

namespace App\Repository;

use App\Entity\Claim;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Claim>
 */
class ClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Claim::class);
    }

    public function save(Claim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Claim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find claim by Ki address
     */
    public function findByKiAddress(string $kiAddress): ?Claim
    {
        return $this->findOneBy(['kiAddress' => $kiAddress]);
    }

    /**
     * Find claim by Ethereum address
     */
    public function findByEthAddress(string $ethAddress): ?Claim
    {
        return $this->findOneBy(['ethAddress' => $ethAddress]);
    }

    /**
     * Find all claims by status
     * @return Claim[]
     */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['createdAt' => 'DESC']);
    }

    /**
     * Count claims by status
     */
    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    /**
     * Count completed claims
     */
    public function countCompleted(): int
    {
        return $this->count(['status' => 'completed']);
    }

    /**
     * Get total distributed amount (completed claims)
     */
    public function sumDistributed(): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.amount) as total')
            ->where('c.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get total amount by status
     */
    public function getTotalAmountByStatus(string $status): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.amount) as total')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}

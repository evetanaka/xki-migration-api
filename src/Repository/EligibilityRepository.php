<?php

namespace App\Repository;

use App\Entity\Eligibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EligibilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Eligibility::class);
    }

    public function save(Eligibility $eligibility, bool $flush = true): void
    {
        $this->getEntityManager()->persist($eligibility);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countEligible(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.eligible = :eligible')
            ->setParameter('eligible', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countClaimed(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.claimed = :claimed')
            ->setParameter('claimed', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Sum balances from eligibility table for wallets that have an approved or completed claim.
     * Returns total in uxki.
     */
    public function sumClaimedBalances(): string
    {
        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery(
            'SELECT SUM(CAST(e.balance AS BIGINT)) as total
             FROM eligibility e
             INNER JOIN claims c ON c.ki_address = e.ki_address
             WHERE c.status IN (:pending, :approved, :completed)',
            ['pending' => 'pending', 'approved' => 'approved', 'completed' => 'completed']
        )->fetchOne();

        return $result ?? '0';
    }
}

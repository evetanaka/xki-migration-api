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
}

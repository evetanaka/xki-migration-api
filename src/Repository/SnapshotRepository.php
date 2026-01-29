<?php

namespace App\Repository;

use App\Entity\Snapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Snapshot>
 */
class SnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Snapshot::class);
    }

    public function save(Snapshot $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Snapshot $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find snapshot by Ki address
     */
    public function findByKiAddress(string $kiAddress): ?Snapshot
    {
        return $this->find($kiAddress);
    }

    /**
     * Get total balance sum
     */
    public function getTotalBalance(): string
    {
        $qb = $this->createQueryBuilder('s')
            ->select('SUM(s.balance) as total')
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($qb ?? '0');
    }
}

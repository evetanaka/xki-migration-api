<?php

namespace App\Repository;

use App\Entity\Nonce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Nonce>
 */
class NonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Nonce::class);
    }

    public function save(Nonce $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Nonce $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find nonce by value
     */
    public function findByNonce(string $nonce): ?Nonce
    {
        return $this->find($nonce);
    }

    /**
     * Check if nonce is valid (exists, not used, not expired)
     */
    public function isNonceValid(string $nonce): bool
    {
        $entity = $this->find($nonce);
        
        if (!$entity) {
            return false;
        }

        if ($entity->isUsed()) {
            return false;
        }

        if ($entity->getExpiresAt() < new \DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Mark nonce as used
     */
    public function markAsUsed(string $nonce): bool
    {
        $entity = $this->find($nonce);
        
        if (!$entity) {
            return false;
        }

        $entity->setUsed(true);
        $this->save($entity, true);

        return true;
    }

    /**
     * Delete expired nonces
     */
    public function deleteExpiredNonces(): int
    {
        $qb = $this->createQueryBuilder('n')
            ->delete()
            ->where('n.expiresAt < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery();

        return $qb->execute();
    }
}

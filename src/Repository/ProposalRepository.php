<?php

namespace App\Repository;

use App\Entity\Proposal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proposal::class);
    }

    public function findActiveProposals(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.startDate <= :now')
            ->andWhere('p.endDate >= :now')
            ->setParameter('status', Proposal::STATUS_ACTIVE)
            ->setParameter('now', $now)
            ->orderBy('p.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestProposals(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status != :draft')
            ->setParameter('draft', Proposal::STATUS_DRAFT)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllPublic(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status != :draft')
            ->setParameter('draft', Proposal::STATUS_DRAFT)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getNextProposalNumber(): string
    {
        $lastProposal = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextNumber = $lastProposal ? $lastProposal->getId() + 1 : 1;
        
        return 'KIP-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function updateExpiredProposals(): int
    {
        $now = new \DateTime();
        
        $qb = $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':ended')
            ->where('p.status = :active')
            ->andWhere('p.endDate < :now')
            ->setParameter('ended', Proposal::STATUS_ENDED)
            ->setParameter('active', Proposal::STATUS_ACTIVE)
            ->setParameter('now', $now);

        return $qb->getQuery()->execute();
    }
}

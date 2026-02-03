<?php

namespace App\Repository;

use App\Entity\Vote;
use App\Entity\Proposal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findVoteByAddressAndProposal(string $kiAddress, Proposal $proposal): ?Vote
    {
        return $this->createQueryBuilder('v')
            ->where('v.kiAddress = :address')
            ->andWhere('v.proposal = :proposal')
            ->setParameter('address', $kiAddress)
            ->setParameter('proposal', $proposal)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasVoted(string $kiAddress, Proposal $proposal): bool
    {
        return $this->findVoteByAddressAndProposal($kiAddress, $proposal) !== null;
    }

    public function findVotesByProposal(Proposal $proposal): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.proposal = :proposal')
            ->setParameter('proposal', $proposal)
            ->orderBy('v.votedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findVotesByAddress(string $kiAddress): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.kiAddress = :address')
            ->setParameter('address', $kiAddress)
            ->orderBy('v.votedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getVoteStatsByProposal(Proposal $proposal): array
    {
        $votes = $this->createQueryBuilder('v')
            ->select('v.voteChoice, SUM(v.votingPower) as totalPower, COUNT(v.id) as count')
            ->where('v.proposal = :proposal')
            ->setParameter('proposal', $proposal)
            ->groupBy('v.voteChoice')
            ->getQuery()
            ->getResult();

        $stats = [
            'for' => ['power' => '0', 'count' => 0],
            'against' => ['power' => '0', 'count' => 0],
            'abstain' => ['power' => '0', 'count' => 0],
        ];

        foreach ($votes as $vote) {
            $stats[$vote['voteChoice']] = [
                'power' => $vote['totalPower'] ?? '0',
                'count' => (int) $vote['count'],
            ];
        }

        return $stats;
    }
}

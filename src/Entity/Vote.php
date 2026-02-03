<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\VoteRepository')]
#[ORM\Table(name: 'votes')]
#[ORM\UniqueConstraint(name: 'unique_vote', columns: ['proposal_id', 'ki_address'])]
class Vote
{
    public const VOTE_FOR = 'for';
    public const VOTE_AGAINST = 'against';
    public const VOTE_ABSTAIN = 'abstain';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Proposal::class, inversedBy: 'votes')]
    #[ORM\JoinColumn(name: 'proposal_id', nullable: false, onDelete: 'CASCADE')]
    private Proposal $proposal;

    #[ORM\Column(name: 'ki_address', type: 'string', length: 255)]
    private string $kiAddress;

    #[ORM\Column(name: 'vote_choice', type: 'string', length: 20)]
    private string $voteChoice;

    #[ORM\Column(name: 'voting_power', type: 'string', length: 255)]
    private string $votingPower;

    #[ORM\Column(type: 'text')]
    private string $signature;

    #[ORM\Column(name: 'pub_key', type: 'text')]
    private string $pubKey;

    #[ORM\Column(name: 'voted_at', type: 'datetime')]
    private \DateTimeInterface $votedAt;

    public function __construct()
    {
        $this->votedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProposal(): Proposal
    {
        return $this->proposal;
    }

    public function setProposal(Proposal $proposal): self
    {
        $this->proposal = $proposal;
        return $this;
    }

    public function getKiAddress(): string
    {
        return $this->kiAddress;
    }

    public function setKiAddress(string $kiAddress): self
    {
        $this->kiAddress = $kiAddress;
        return $this;
    }

    public function getVoteChoice(): string
    {
        return $this->voteChoice;
    }

    public function setVoteChoice(string $voteChoice): self
    {
        if (!in_array($voteChoice, [self::VOTE_FOR, self::VOTE_AGAINST, self::VOTE_ABSTAIN])) {
            throw new \InvalidArgumentException('Invalid vote choice');
        }
        $this->voteChoice = $voteChoice;
        return $this;
    }

    public function getVotingPower(): string
    {
        return $this->votingPower;
    }

    public function setVotingPower(string $votingPower): self
    {
        $this->votingPower = $votingPower;
        return $this;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function getPubKey(): string
    {
        return $this->pubKey;
    }

    public function setPubKey(string $pubKey): self
    {
        $this->pubKey = $pubKey;
        return $this;
    }

    public function getVotedAt(): \DateTimeInterface
    {
        return $this->votedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'proposalId' => $this->proposal->getId(),
            'kiAddress' => $this->kiAddress,
            'voteChoice' => $this->voteChoice,
            'votingPower' => $this->votingPower,
            'votedAt' => $this->votedAt->format('c'),
        ];
    }
}

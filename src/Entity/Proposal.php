<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: 'App\Repository\ProposalRepository')]
#[ORM\Table(name: 'proposals')]
#[ORM\HasLifecycleCallbacks]
class Proposal
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';
    public const STATUS_PASSED = 'passed';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'string', length: 255)]
    private string $proposalNumber;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: 'string', length: 255)]
    private string $votesFor = '0';

    #[ORM\Column(type: 'string', length: 255)]
    private string $votesAgainst = '0';

    #[ORM\Column(type: 'string', length: 255)]
    private string $votesAbstain = '0';

    #[ORM\Column(type: 'integer')]
    private int $voterCount = 0;

    #[ORM\Column(type: 'string', length: 255)]
    private string $quorum = '0';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(mappedBy: 'proposal', targetEntity: Vote::class, cascade: ['persist', 'remove'])]
    private Collection $votes;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getProposalNumber(): string
    {
        return $this->proposalNumber;
    }

    public function setProposalNumber(string $proposalNumber): self
    {
        $this->proposalNumber = $proposalNumber;
        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getVotesFor(): string
    {
        return $this->votesFor;
    }

    public function setVotesFor(string $votesFor): self
    {
        $this->votesFor = $votesFor;
        return $this;
    }

    public function addVotesFor(string $amount): self
    {
        $this->votesFor = bcadd($this->votesFor, $amount, 0);
        return $this;
    }

    public function getVotesAgainst(): string
    {
        return $this->votesAgainst;
    }

    public function setVotesAgainst(string $votesAgainst): self
    {
        $this->votesAgainst = $votesAgainst;
        return $this;
    }

    public function addVotesAgainst(string $amount): self
    {
        $this->votesAgainst = bcadd($this->votesAgainst, $amount, 0);
        return $this;
    }

    public function getVotesAbstain(): string
    {
        return $this->votesAbstain;
    }

    public function setVotesAbstain(string $votesAbstain): self
    {
        $this->votesAbstain = $votesAbstain;
        return $this;
    }

    public function addVotesAbstain(string $amount): self
    {
        $this->votesAbstain = bcadd($this->votesAbstain, $amount, 0);
        return $this;
    }

    public function getVoterCount(): int
    {
        return $this->voterCount;
    }

    public function setVoterCount(int $voterCount): self
    {
        $this->voterCount = $voterCount;
        return $this;
    }

    public function incrementVoterCount(): self
    {
        $this->voterCount++;
        return $this;
    }

    public function getQuorum(): string
    {
        return $this->quorum;
    }

    public function setQuorum(string $quorum): self
    {
        $this->quorum = $quorum;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function getTotalVotes(): string
    {
        return bcadd(bcadd($this->votesFor, $this->votesAgainst, 0), $this->votesAbstain, 0);
    }

    public function isActive(): bool
    {
        $now = new \DateTime();
        return $this->status === self::STATUS_ACTIVE 
            && $now >= $this->startDate 
            && $now <= $this->endDate;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'proposalNumber' => $this->proposalNumber,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'startDate' => $this->startDate->format('c'),
            'endDate' => $this->endDate->format('c'),
            'votesFor' => $this->votesFor,
            'votesAgainst' => $this->votesAgainst,
            'votesAbstain' => $this->votesAbstain,
            'voterCount' => $this->voterCount,
            'quorum' => $this->quorum,
            'totalVotes' => $this->getTotalVotes(),
            'isActive' => $this->isActive(),
            'createdAt' => $this->createdAt->format('c'),
        ];
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\ClaimRepository')]
#[ORM\Table(name: 'claims')]
class Claim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $kiAddress;

    #[ORM\Column(type: 'string', length: 255)]
    private string $ethAddress;

    #[ORM\Column(type: 'bigint')]
    private int $amount = 0;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, approved, rejected, completed

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $txHash = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $pubKey = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $nonce = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $claimedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEthAddress(): string
    {
        return $this->ethAddress;
    }

    public function setEthAddress(string $ethAddress): self
    {
        $this->ethAddress = $ethAddress;
        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;
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

    public function getTxHash(): ?string
    {
        return $this->txHash;
    }

    public function setTxHash(?string $txHash): self
    {
        $this->txHash = $txHash;
        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function getPubKey(): ?string
    {
        return $this->pubKey;
    }

    public function setPubKey(?string $pubKey): self
    {
        $this->pubKey = $pubKey;
        return $this;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function setNonce(?string $nonce): self
    {
        $this->nonce = $nonce;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getClaimedAt(): ?\DateTimeInterface
    {
        return $this->claimedAt;
    }

    public function setClaimedAt(?\DateTimeInterface $claimedAt): self
    {
        $this->claimedAt = $claimedAt;
        return $this;
    }
}

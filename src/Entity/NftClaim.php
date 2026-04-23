<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\NftClaimRepository')]
#[ORM\Table(name: 'nft_claim')]
class NftClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $kiAddress;

    #[ORM\Column(type: 'string', length: 42)]
    private string $ethAddress;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
    private string $totalAllocation;

    #[ORM\Column(type: 'integer')]
    private int $nftCount;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = 'pending'; // pending, processing, completed, failed

    #[ORM\Column(type: 'text')]
    private string $signature;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $pubKey = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $nonce;

    #[ORM\Column(type: 'text')]
    private string $signedMessage;

    #[ORM\Column(type: 'string', length: 66, nullable: true)]
    private ?string $txHash = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    /** @var Collection<int, NftAsset> */
    #[ORM\OneToMany(mappedBy: 'claim', targetEntity: NftAsset::class)]
    private Collection $nftAssets;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->nftAssets = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getKiAddress(): string { return $this->kiAddress; }
    public function setKiAddress(string $kiAddress): self { $this->kiAddress = $kiAddress; return $this; }
    public function getEthAddress(): string { return $this->ethAddress; }
    public function setEthAddress(string $ethAddress): self { $this->ethAddress = $ethAddress; return $this; }
    public function getTotalAllocation(): string { return $this->totalAllocation; }
    public function setTotalAllocation(string $totalAllocation): self { $this->totalAllocation = $totalAllocation; return $this; }
    public function getNftCount(): int { return $this->nftCount; }
    public function setNftCount(int $nftCount): self { $this->nftCount = $nftCount; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getSignature(): string { return $this->signature; }
    public function setSignature(string $signature): self { $this->signature = $signature; return $this; }
    public function getPubKey(): ?string { return $this->pubKey; }
    public function setPubKey(?string $pubKey): self { $this->pubKey = $pubKey; return $this; }
    public function getNonce(): string { return $this->nonce; }
    public function setNonce(string $nonce): self { $this->nonce = $nonce; return $this; }
    public function getSignedMessage(): string { return $this->signedMessage; }
    public function setSignedMessage(string $signedMessage): self { $this->signedMessage = $signedMessage; return $this; }
    public function getTxHash(): ?string { return $this->txHash; }
    public function setTxHash(?string $txHash): self { $this->txHash = $txHash; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getProcessedAt(): ?\DateTimeInterface { return $this->processedAt; }
    public function setProcessedAt(?\DateTimeInterface $processedAt): self { $this->processedAt = $processedAt; return $this; }
    /** @return Collection<int, NftAsset> */
    public function getNftAssets(): Collection { return $this->nftAssets; }
}

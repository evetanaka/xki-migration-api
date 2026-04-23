<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\NftAssetRepository')]
#[ORM\Table(name: 'nft_asset')]
#[ORM\UniqueConstraint(name: 'uniq_collection_token', columns: ['collection', 'token_id'])]
#[ORM\Index(name: 'idx_nft_asset_owner', columns: ['owner'])]
class NftAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $collection;

    #[ORM\Column(type: 'string', length: 64)]
    private string $tokenId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $owner;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 512)]
    private string $image;

    #[ORM\Column(type: 'string', length: 32)]
    private string $scarcity;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $personality = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $geographical = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $time = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $nationality = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $assetId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 6)]
    private string $allocation = '0';

    #[ORM\ManyToOne(targetEntity: NftClaim::class, inversedBy: 'nftAssets')]
    #[ORM\JoinColumn(name: 'claim_id', referencedColumnName: 'id', nullable: true)]
    private ?NftClaim $claim = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getCollection(): string { return $this->collection; }
    public function setCollection(string $collection): self { $this->collection = $collection; return $this; }
    public function getTokenId(): string { return $this->tokenId; }
    public function setTokenId(string $tokenId): self { $this->tokenId = $tokenId; return $this; }
    public function getOwner(): string { return $this->owner; }
    public function setOwner(string $owner): self { $this->owner = $owner; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getImage(): string { return $this->image; }
    public function setImage(string $image): self { $this->image = $image; return $this; }
    public function getScarcity(): string { return $this->scarcity; }
    public function setScarcity(string $scarcity): self { $this->scarcity = $scarcity; return $this; }
    public function getPersonality(): ?string { return $this->personality; }
    public function setPersonality(?string $personality): self { $this->personality = $personality; return $this; }
    public function getGeographical(): ?string { return $this->geographical; }
    public function setGeographical(?string $geographical): self { $this->geographical = $geographical; return $this; }
    public function getTime(): ?string { return $this->time; }
    public function setTime(?string $time): self { $this->time = $time; return $this; }
    public function getNationality(): ?string { return $this->nationality; }
    public function setNationality(?string $nationality): self { $this->nationality = $nationality; return $this; }
    public function getAssetId(): ?string { return $this->assetId; }
    public function setAssetId(?string $assetId): self { $this->assetId = $assetId; return $this; }
    public function getShortDescription(): ?string { return $this->shortDescription; }
    public function setShortDescription(?string $shortDescription): self { $this->shortDescription = $shortDescription; return $this; }
    public function getAllocation(): string { return $this->allocation; }
    public function setAllocation(string $allocation): self { $this->allocation = $allocation; return $this; }
    public function getClaim(): ?NftClaim { return $this->claim; }
    public function setClaim(?NftClaim $claim): self { $this->claim = $claim; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}

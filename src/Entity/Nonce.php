<?php

namespace App\Entity;

use App\Repository\NonceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NonceRepository::class)]
#[ORM\Table(name: 'nonces')]
class Nonce
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 64)]
    private ?string $nonce = null;

    #[ORM\Column(type: Types::STRING, length: 65)]
    private ?string $kiAddress = null;

    #[ORM\Column(type: Types::STRING, length: 42)]
    private ?string $ethAddress = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private ?bool $used = false;

    public function __construct()
    {
        $this->used = false;
    }

    public function getNonce(): ?string
    {
        return $this->nonce;
    }

    public function setNonce(string $nonce): static
    {
        $this->nonce = $nonce;
        return $this;
    }

    public function getKiAddress(): ?string
    {
        return $this->kiAddress;
    }

    public function setKiAddress(string $kiAddress): static
    {
        $this->kiAddress = $kiAddress;
        return $this;
    }

    public function getEthAddress(): ?string
    {
        return $this->ethAddress;
    }

    public function setEthAddress(string $ethAddress): static
    {
        $this->ethAddress = $ethAddress;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): ?bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): static
    {
        $this->used = $used;
        return $this;
    }
}

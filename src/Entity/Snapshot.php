<?php

namespace App\Entity;

use App\Repository\SnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SnapshotRepository::class)]
#[ORM\Table(name: 'snapshots')]
class Snapshot
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 65)]
    private ?string $kiAddress = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $balance = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getBalance(): ?string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}

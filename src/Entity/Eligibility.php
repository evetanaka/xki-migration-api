<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\EligibilityRepository')]
#[ORM\Table(name: 'eligibility')]
class Eligibility
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $kiAddress;

    #[ORM\Column(type: 'string', length: 255)]
    private string $balance;

    #[ORM\Column(type: 'boolean')]
    private bool $eligible = true;

    #[ORM\Column(type: 'boolean')]
    private bool $claimed = false;

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

    public function getBalance(): string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;
        return $this;
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    public function setEligible(bool $eligible): self
    {
        $this->eligible = $eligible;
        return $this;
    }

    public function isClaimed(): bool
    {
        return $this->claimed;
    }

    public function setClaimed(bool $claimed): self
    {
        $this->claimed = $claimed;
        return $this;
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'nft_claim_config')]
class NftClaimConfig
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $key;

    #[ORM\Column(type: 'text')]
    private string $value;

    public function getKey(): string { return $this->key; }
    public function setKey(string $key): self { $this->key = $key; return $this; }
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): self { $this->value = $value; return $this; }
}

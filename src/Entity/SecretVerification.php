<?php

namespace App\Entity;

use App\Repository\SecretVerificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SecretVerificationRepository::class)]
class SecretVerification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $verified;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $verifier;

    #[ORM\Column(type: 'datetime')]
    private $verifiedAt;

    #[ORM\Column(type: 'integer')]
        #[Groups(["user:read"])]
    private $sport;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVerified(): ?User
    {
        return $this->verified;
    }

    public function setVerified(?User $verified): self
    {
        $this->verified = $verified;

        return $this;
    }

    public function getVerifier(): ?User
    {
        return $this->verifier;
    }

    public function setVerifier(?User $verifier): self
    {
        $this->verifier = $verifier;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTime
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(\DateTime $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getSport(): ?int
    {
        return $this->sport;
    }

    public function setSport(int $sport): self
    {
        $this->sport = $sport;

        return $this;
    }
}

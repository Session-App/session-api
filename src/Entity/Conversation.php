<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinTable;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["collection:conversations", "conversations:read:light", "collection:conversations:foreign", "conversation:id"])]
    private ?int $id = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(["collection:conversations", "conversations:read:light", "collection:conversations:foreign", "item:conversation:foreign"])]
    private ?string $name = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[JoinTable("conversation_member")]
    #[Groups(["item:conversation"])]
    private Collection $members;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["collection:conversations"])]
    private ?User $administrator = null;

    #[ORM\ManyToOne]
    #[Groups(["collection:conversations"])]
    private ?User $recipient = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[Groups(["collection:conversations"])]
    private ?Message $lastMessage = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[JoinTable("conversation_awaiting_member")]
    #[Groups(["item:conversation"])]
    private Collection $awaitingMembers;

    #[ORM\Column]
    #[Groups(["collection:conversations", "item:conversation:foreign"])]
    private ?bool $private = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["item:conversation", "collection:conversations"])]
    private ?int $sport = null;

    #[ORM\Column(nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(nullable: true)]
    private ?float $lon = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["item:conversation", "collection:conversations"])]
    private ?string $locationName = null;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->awaitingMembers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): self
    {
        if (!$this->members->contains($member) && $this->getAdministrator() !== $member->getId()) {
            $this->members->add($member);
        }

        return $this;
    }

    public function removeMember(User $member): self
    {
        $this->members->removeElement($member);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAdministrator(): ?User
    {
        return $this->administrator;
    }

    public function setAdministrator(?User $administrator): self
    {
        $this->administrator = $administrator;

        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getLastMessage(): ?Message
    {
        return $this->lastMessage;
    }

    public function setLastMessage(?Message $lastMessage): self
    {
        $this->lastMessage = $lastMessage;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAwaitingMembers(): Collection
    {
        return $this->awaitingMembers;
    }

    public function addAwaitingMember(User $awaitingMember): self
    {
        if (!$this->awaitingMembers->contains($awaitingMember)) {
            $this->awaitingMembers->add($awaitingMember);
        }

        return $this;
    }

    public function removeAwaitingMember(User $awaitingMember): self
    {
        $this->awaitingMembers->removeElement($awaitingMember);

        return $this;
    }

    public function isPrivate(): ?bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): self
    {
        $this->private = $private;

        return $this;
    }

    public function getSport(): ?int
    {
        return $this->sport;
    }

    public function setSport(?int $sport): self
    {
        $this->sport = $sport;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLon(?float $lon): self
    {
        $this->lon = $lon;

        return $this;
    }

    public function getLocationName(): ?string
    {
        return $this->locationName;
    }

    public function setLocationName(?string $locationName): self
    {
        $this->locationName = $locationName;

        return $this;
    }
}

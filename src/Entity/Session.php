<?php

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["collection:sessions", "item:session", "item:spot", "session:id"])]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["item:session"])]
    private $createdBy;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(["item:session"])]
    private $description;

    #[ORM\ManyToOne(targetEntity: Spot::class, inversedBy: 'sessions', cascade: ["persist"])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(["collection:sessions", "item:session"])]
    private $spot;

    #[ORM\Column(type: 'datetime')]
    #[Groups(["collection:sessions", "item:session", "item:spot"])]
    private $date;

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[Groups(["item:session"])]
    private $participants;

    #[ORM\OneToMany(mappedBy: 'session', targetEntity: CommentSession::class)]
    #[Groups(["item:session"])]
    private $comments;

    #[ORM\ManyToOne]
    private ?Conversation $private = null;

    #[ORM\Column(nullable: true)]
    #[Groups(["item:session"])]
    private ?int $maxParticipants = null;


    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->content = new ArrayCollection();
        $this->commentSessions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): ?self
    {
        $this->id = $id;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSpot(): ?Spot
    {
        return $this->spot;
    }

    public function setSpot(?Spot $spot): self
    {
        $this->spot = $spot;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants[] = $participant;
        }

        return $this;
    }

    public function removeParticipant(User $participant): self
    {
        $this->participants->removeElement($participant);

        return $this;
    }

    /**
     * @return Collection|CommentSession[]
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(CommentSession $commentSession): self
    {
        if (!$this->comments->contains($commentSession)) {
            $this->comments[] = $commentSession;
            $commentSession->setSession($this);
        }

        return $this;
    }

    public function removeCommentSession(CommentSession $commentSession): self
    {
        if ($this->comments->removeElement($commentSession)) {
            // set the owning side to null (unless already changed)
            if ($commentSession->getSession() === $this) {
                $commentSession->setSession(null);
            }
        }

        return $this;
    }

    public function getPrivate(): ?Conversation
    {
        return $this->private;
    }

    public function setPrivate(?Conversation $private): self
    {
        $this->private = $private;

        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(?int $maxParticipants): self
    {
        $this->maxParticipants = $maxParticipants;

        return $this;
    }

    public function isFull(): ?bool
    {
        if ($this->maxParticipants === null) {
            return false;
        } else {
            return $this->maxParticipants === count($this->participants);
        }
    }
}

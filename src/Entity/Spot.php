<?php

namespace App\Entity;

use App\Repository\SpotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SpotRepository::class)]
class Spot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
        #[Groups(["collection:spots","collection:sessions", "item:session", "min:spot"])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
        #[Groups(["collection:spots","collection:sessions","item:spot","min:spot"])]
    private $name;

    #[ORM\Column(type: 'float')]
        #[Groups(["collection:spots","item:spot","collection:sessions"])]
    private $lon;

    #[ORM\Column(type: 'float')]
        #[Groups(["collection:spots","item:spot","collection:sessions"])]
    private $lat;

    #[ORM\Column(type: 'text', nullable: true)]
        #[Groups(["item:spot"])]
    private $description;

    #[ORM\Column(type: 'datetime_immutable')]
        #[Groups(["min:spot"])]
    private $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private $updatedAt;

    #[ORM\Column(type: 'array', nullable: true)]
        #[Groups(["item:spot"])]
    private $videos = [];

    #[ORM\OneToMany(mappedBy: 'spot', targetEntity: CommentSpot::class, orphanRemoval: true)]
        #[Groups(["item:spot"])]
    private $comments;

    #[ORM\OneToMany(mappedBy: 'spot', targetEntity: Picture::class)]
        #[Groups(["item:spot","item:spot:brief"])]
    private $pictures;

    #[ORM\OneToMany(mappedBy: 'spot', targetEntity: Session::class)]
    private $sessions;

    #[ORM\Column(type: 'simple_array', nullable: true)]
        #[Groups(["item:spot"])]
    private $tags = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
        #[Groups(["item:spot"])]
    private $addedBy;

    #[ORM\Column(type: 'integer', nullable: true)]
        #[Groups(["item:spot","min:spot", "sport"])]
    private $sport;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $secret;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private $validated;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->pictures = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->validated = false;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLon(float $lon): self
    {
        $this->lon = $lon;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(float $lat): self
    {
        $this->lat = $lat;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }


    public function getVideos(): ?array
    {
        return $this->videos;
    }

    public function setVideos(?array $videos): self
    {
        $this->videos = $videos;

        return $this;
    }

    /**
     * @return Collection|CommentSpot[]
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }




    public function addComment(CommentSpot $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setSpot($this);
        }

        return $this;
    }

    public function removeComment(CommentSpot $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getSpot() === $this) {
                $comment->setSpot(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection|Picture[]
     */
    public function getPictures(): Collection
    {
        return $this->pictures;
    }

    public function addPicture(Picture $picture): self
    {
        if (!$this->pictures->contains($picture)) {
            $this->pictures[] = $picture;
            $picture->setSpot($this);
        }

        return $this;
    }

    public function removePicture(Picture $picture): self
    {
        if ($this->pictures->removeElement($picture)) {
            // set the owning side to null (unless already changed)
            if ($picture->getSpot() === $this) {
                $picture->setSpot(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Session[]
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(Session $session): self
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions[] = $session;
            $session->setSpot($this);
        }

        return $this;
    }

    public function removeSession(Session $session): self
    {
        if ($this->sessions->removeElement($session)) {
            // set the owning side to null (unless already changed)
            if ($session->getSpot() === $this) {
                $session->setSpot(null);
            }
        }

        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): self
    {
        $this->addedBy = $addedBy;

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

    public function getSecret(): ?bool
    {
        return $this->secret;
    }

    public function setSecret(?bool $secret): self
    {
        $this->secret = $secret;

        return $this;
    }

    public function getValidated(): ?bool
    {
        return $this->validated;
    }

    public function setValidated(?bool $validated): self
    {
        $this->validated = $validated;

        return $this;
    }
}

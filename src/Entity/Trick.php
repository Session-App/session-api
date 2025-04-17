<?php

namespace App\Entity;

use App\Repository\TrickRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinTable;
use Symfony\Component\Serializer\Annotation\Groups;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Translatable\Translatable;

#[ORM\Entity(repositoryClass: TrickRepository::class)]
class Trick implements Translatable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["trick:collection"])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Translatable]
    #[Groups(["trick:read", "trick:collection"])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(["trick:read"])]
    private ?int $sport = null;

    #[ORM\Column]
    #[Groups(["trick:read"])]
    private ?int $points = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(["trick:read"])]
    #[Gedmo\Translatable]
    private ?string $video = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(["trick:read"])]
    #[Gedmo\Translatable]
    private ?array $description = [];

    /**
     * Used locale to override Translation listener`s locale
     * this is not a mapped field of entity metadata, just a simple property
     */
    #[Gedmo\Locale]
    private $locale;

    #[ORM\Column]
    #[Groups(["trick:read"])]
    private ?int $amountMastered = null;

    #[ORM\Column]
    #[Groups(["trick:read"])]
    private ?int $amountLearning = null;

    #[ORM\ManyToMany(targetEntity: TrickTag::class, inversedBy: 'tricks')]
    #[JoinTable("tags_trick_relation")]
    private Collection $tags;

    #[ORM\Column(length: 5)]
    #[Groups(["trick:read"])]
    private ?string $preview = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $variationOf = null;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getSport(): ?int
    {
        return $this->sport;
    }

    public function setSport(int $sport): self
    {
        $this->sport = $sport;

        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;

        return $this;
    }

    public function getVideo(): ?string
    {
        return $this->video;
    }

    public function setVideo(?string $video): self
    {
        $this->video = $video;

        return $this;
    }

    public function getDescription(): array
    {
        return $this->description;
    }

    public function setDescription(array $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setTranslatableLocale($locale)
    {
        $this->locale = $locale;
    }

    public function getAmountTraining(): ?int
    {
        return $this->amountTraining;
    }

    public function getAmountMastered(): ?int
    {
        return $this->amountMastered;
    }

    public function increaseAmountMastered(int $amount): self
    {
        $this->amountMastered += $amount;

        return $this;
    }

    public function setAmountMastered(int $amountMastered): self
    {
        $this->amountMastered = $amountMastered;

        return $this;
    }

    public function getAmountLearning(): ?int
    {
        return $this->amountLearning;
    }

    public function setAmountLearning(int $amountLearning): self
    {
        $this->amountLearning = $amountLearning;

        return $this;
    }

    public function increaseAmountLearning(int $amount): self
    {
        $this->amountLearning += $amount;

        return $this;
    }

    /**
     * @return Collection<int, TrickTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TrickTag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(TrickTag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getPreview(): ?string
    {
        return $this->preview;
    }

    public function setPreview(string $preview): self
    {
        $this->preview = $preview;

        return $this;
    }

    public function getVariationOf(): ?self
    {
        return $this->variationOf;
    }

    public function setVariationOf(?self $variationOf): self
    {
        $this->variationOf = $variationOf;

        return $this;
    }
}

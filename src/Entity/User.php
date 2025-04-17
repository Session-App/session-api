<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinTable;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, JWTUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(["user:read", "user:read:light"])]
    private $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private $email;

    #[ORM\Column(type: 'json')]
    #[Groups(["user:read"])]
    private $roles = [];

    #[ORM\Column(type: 'string')]
    private $password;

    #[ORM\Column(type: 'string', length: 22)]
    #[Groups(["user:read", "user:read:light"])]
    private $username;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["user:read", "user:read:light"])]
    private $profilePicture;

    #[ORM\Column(type: 'simple_array', nullable: true)]
    #[Groups(["user:read"])]
    private $favoriteSports = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(["user:read"])]
    private $bio;

    #[ORM\ManyToMany(targetEntity: Spot::class)]
    private $favoriteSpots;

    #[ORM\Column(type: 'integer', nullable: false)]
    private $contribution;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private $referredBy;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $pushNotificationsToken;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $lang = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $lastLocationLon = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $lastLocationLat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetRequestedAt = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    #[Groups(["user:read:private"])]
    private array $disabledConversationNotifications = [];

    // todo : remove this attribute, use the connection entity instead
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastConnection = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $platform = null;

    #[ORM\Column]
    private ?int $version = null;

    #[ORM\Column]
    private ?bool $newsletterSubscribed = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $newsletter_token = null;

    #[ORM\Column]
    private ?bool $loggedOut = null;

    #[ORM\ManyToMany(targetEntity: self::class)]
    #[JoinTable("blocked_users")]
    private Collection $blockedUsers;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $tricksMastered = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $tricksLearning = [];

    #[ORM\Column(type: 'json')]
    #[Groups(["user:read"])]
    private array $sportXP = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'json')]
    private array $masteredTags = [];

    public function __construct()
    {
        $this->favoriteSpots = new ArrayCollection();
        $this->blockedUsers = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }


    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }


    public static function createFromPayload($username, array $payload)
    {
        return (new User())->setUsername($username);
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getFavoriteSports(): ?array
    {
        return $this->favoriteSports;
    }

    public function setFavoriteSports(array $favoriteSports): self
    {
        $this->favoriteSports = $favoriteSports;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

        return $this;
    }

    /**
     * @return Collection<int, Spot>
     */
    public function getFavoriteSpots(): Collection
    {
        return $this->favoriteSpots;
    }

    public function addFavoriteSpot(Spot $favoriteSpot): self
    {
        if (!$this->favoriteSpots->contains($favoriteSpot)) {
            $this->favoriteSpots[] = $favoriteSpot;
        }

        return $this;
    }

    public function removeFavoriteSpot(Spot $favoriteSpot): self
    {
        $this->favoriteSpots->removeElement($favoriteSpot);

        return $this;
    }

    public function getContribution(): ?int
    {
        return $this->contribution;
    }

    public function setContribution(?int $contribution): self
    {
        $this->contribution = $contribution;

        return $this;
    }

    public function increaseContribution(?int $contribution): self
    {
        $this->contribution += $contribution;

        return $this;
    }

    public function getReferredBy(): ?self
    {
        return $this->referredBy;
    }

    public function setReferredBy(?self $referredBy): self
    {
        $this->referredBy = $referredBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPushNotificationsToken(): ?string
    {
        return $this->pushNotificationsToken;
    }

    public function setPushNotificationsToken(?string $pushNotificationsToken): self
    {
        $this->pushNotificationsToken = $pushNotificationsToken;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getLastLocationLon(): ?string
    {
        return $this->lastLocationLon;
    }

    public function setLastLocationLon(?string $lastLocationLon): self
    {
        $this->lastLocationLon = $lastLocationLon;

        return $this;
    }

    public function getLastLocationLat(): ?string
    {
        return $this->lastLocationLat;
    }

    public function setLastLocationLat(?string $lastLocationLat): self
    {
        $this->lastLocationLat = $lastLocationLat;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function hasPasswordResetTokenExpired(): bool
    {
        if (null === $this->getPasswordResetRequestedAt()) {
            return false;
        }

        $requestedAtTimestamp = $this->getPasswordResetRequestedAt()->getTimestamp();

        return time() - $requestedAtTimestamp > 3600;
    }

    public function getPasswordResetRequestedAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetRequestedAt;
    }

    public function setPasswordResetRequestedAt(?\DateTimeImmutable $passwordResetRequestedAt): self
    {
        $this->passwordResetRequestedAt = $passwordResetRequestedAt;

        return $this;
    }

    public function getDisabledConversationNotifications(): array
    {
        return $this->disabledConversationNotifications;
    }

    public function setDisabledConversationNotifications(array $disabledConversationNotifications): self
    {
        $this->disabledConversationNotifications = $disabledConversationNotifications;

        return $this;
    }

    public function getLastConnection(): ?\DateTimeInterface
    {
        return $this->lastConnection;
    }

    public function setLastConnection(\DateTimeInterface $lastConnection): self
    {
        $this->lastConnection = $lastConnection;

        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function isSubscribedNewsletter(): ?bool
    {
        return $this->newsletterSubscribed;
    }

    public function setNewsletterSubscribed(bool $newsletterSubscribed): self
    {
        $this->newsletterSubscribed = $newsletterSubscribed;

        return $this;
    }

    public function getNewsletterToken(): ?string
    {
        return $this->newsletter_token;
    }

    public function setNewsletterToken(string $newsletter_token): self
    {
        $this->newsletter_token = $newsletter_token;

        return $this;
    }

    public function isLoggedOut(): ?bool
    {
        return $this->loggedOut;
    }

    public function setLoggedOut(bool $loggedOut): self
    {
        $this->loggedOut = $loggedOut;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getBlockedUsers(): Collection
    {
        return $this->blockedUsers;
    }

    public function addBlockedUser(self $blockedUser): self
    {
        if (!$this->blockedUsers->contains($blockedUser)) {
            $this->blockedUsers->add($blockedUser);
        }

        return $this;
    }

    public function removeBlockedUser(self $blockedUser): self
    {
        $this->blockedUsers->removeElement($blockedUser);

        return $this;
    }

    public function getTricksMastered(): array
    {
        return $this->tricksMastered;
    }

    public function setTricksMastered(array $tricksMastered): self
    {
        $this->tricksMastered = $tricksMastered;

        return $this;
    }

    public function addTrickMastered(int $trickId): self
    {
        array_push($this->tricksMastered, $trickId);

        return $this;
    }

    public function removeTrickMastered(int $trickId): self
    {
        // unset($this->tricksMastered[array_search($trickId, $this->tricksMastered)]);
        array_splice($this->tricksMastered, array_search($trickId, $this->tricksMastered), 1);

        return $this;
    }

    public function isTrickMastered(int $trickId): bool
    {
        return in_array($trickId, $this->getTricksMastered());
    }


    public function getTricksLearning(): array
    {
        return $this->tricksLearning;
    }

    public function setTricksLearning(array $tricksLearning): self
    {
        $this->tricksLearning = $tricksLearning;

        return $this;
    }

    public function addTrickLearning(int $trickId): self
    {
        array_push($this->tricksLearning, $trickId);

        return $this;
    }

    public function removeTrickLearning(int $trickId): self
    {
        // unset($this->tricksLearning[array_search($trickId, $this->tricksLearning)]);
        array_splice($this->tricksLearning, array_search($trickId, $this->tricksLearning), 1);

        return $this;
    }

    public function isLearningTrick(int $trickId): bool
    {
        // dd($trickId, $this->getTricksLearning(), in_array($trickId, $this->getTricksLearning()));
        return in_array($trickId, $this->getTricksLearning());
    }

    public function getSportXP(): array
    {
        return $this->sportXP;
    }

    public function setSportXP(array $sportXP): self
    {
        $this->sportXP = $sportXP;

        return $this;
    }

    public function increaseSportXP(int $xp, int $sport): self
    {
        $this->sportXP[$sport] = ($this->sportXP[$sport] ?? 0) + $xp;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getMasteredTags(): array
    {
        return $this->masteredTags;
    }

    public function setMasteredTags(array $masteredTags): self
    {
        $this->masteredTags = $masteredTags;

        return $this;
    }
}

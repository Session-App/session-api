<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    private PaginatorInterface $paginator;

    public function __construct(
        ManagerRegistry $registry,
        private UserPasswordHasherInterface $passwordHasher,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, User::class);
        $this->paginator = $paginator;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function loadUserByIdentifier(string $usernameOrEmail): ?User
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQuery(
            'SELECT u
                FROM App\Entity\User u
                WHERE (u.username = :query
                OR u.email = :query)
                AND u.deletedAt IS NULL'
        )
            ->setParameter('query', $usernameOrEmail)
            ->getOneOrNullResult();
    }

    public function amountOfFavoriteSpots($userId): ?int
    {
        return $this->createQueryBuilder('u')
            ->select('count(s.id)')
            ->leftJoin('u.favoriteSpots', 's')
            ->Where('u.id = :userId')
            ->andWhere('s.validated = true')
            ->setParameter(':userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /*
    public function userFavoriteSpots($userId, $page, $size): ?PaginationInterface
    {
    $qb = $this->createQueryBuilder('u')
               ->leftJoin('u.favoriteSpots', 's')
               ->select('s.id, s.name, s.lat')
               ->Where('u.id = :userId')
               ->andWhere('s.validated = true')
               ->setParameter(':userId', $userId);
    dd($qb->getQuery());

    return $this->paginator->paginate(
        $qb->getQuery(),
        $page,
        $size
    );
    }
         */

    public function isSpotFavorite($userId, $spotId): bool
    {
        $user = $this->createQueryBuilder('u')
            ->join('u.favoriteSpots', 's')
            ->where('s.id = :spotId')
            ->andWhere('u.id = :userId')
            ->setParameter('spotId', $spotId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
        return $user !== null;
    }

    public function searchUsers($username, $hideMe, $userId, $page, $size, $orderBy = 'contribution'): ?array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.username LIKE :usernameApprox')
            ->addOrderBy('locate(:username, u.username)', 'ASC');

        if ($hideMe) {
            $qb->andWhere('u.id != :userId')
                ->setParameter('userId', $userId);
        }
        $qb->addOrderBy('u.contribution', 'DESC');
        //todo : order by friendship
        // if ($orderBy === 'contribution') {
        //     $qb->addOrderBy('u.contribution', 'DESC');
        // } else if ($orderBy === 'friendship') {
        //     $qb->leftJoin('App\Entity\Friendship', 'f', Expr\Join::WITH, '(f.requested = :userId OR f.requester = :userId)')
        //         ->setParameter('userId', $userId)
        //         ->addOrderBy('f.requester', 'DESC');
        // }
        $qb->setMaxResults($size)
            ->setParameter('username', $username)
            ->setParameter('usernameApprox', '%' . $username . '%');

        // dd($qb->getQuery());
        return $qb->getQuery()
            ->getResult();

        /*
        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );*/
    }

    public function findNearbyUsers($lat, $lon, $userId, $sportId): ?array
    {
        $qb = $this->createQueryBuilder('u')
            // the distance is in miles squared
            ->where('POWER(69.1 * (u.lastLocationLat - :userLat), 2) + POWER(69.1 * (:userLon - u.lastLocationLon) * COS(u.lastLocationLat / 57.3), 2) < 347.4921005') // 30km
            ->andWhere('u.id != :userId')
            ->andWhere('FIND_IN_SET(:sportId, u.favoriteSports) != 0')
            ->setParameter(':userLat', $lat)
            ->setParameter(':userLon', $lon)
            ->setParameter(':userId', $userId)
            ->setParameter(':sportId', $sportId);

        //dd($qb->getQuery());


        return $qb->getQuery()->getResult();
    }

    // has to be used everywhere when getting informations about conversations
    public function onlyIfUserIsInConversation(QueryBuilder $qb, int $userId): QueryBuilder
    {
        $qb->andWhere('c.administrator = :userId OR c.recipient = :userId OR :userId MEMBER OF c.members')
            ->setParameter(':userId', $userId);

        return $qb;
    }

    public function getConversationMembers($userId, $conversationId, $page, $size): PaginationInterface
    {
        $qb = $this->createQueryBuilder('u')
            ->join('App\Entity\Conversation', 'c')
            ->where('c.id = :conversationId')
            ->andWhere('u MEMBER OF c.members')
            ->orderBy('u.username')
            ->setParameter(':conversationId', $conversationId);

        $this->onlyIfUserIsInConversation($qb, $userId);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function getConversationAwaitingMembers($userId, $conversationId): array
    {
        $qb = $this->createQueryBuilder('u')
            ->join('App\Entity\Conversation', 'c')
            ->where('c.id = :conversationId')
            ->andWhere('u MEMBER OF c.awaitingMembers')
            ->setParameter(':conversationId', $conversationId);

        $this->onlyIfUserIsInConversation($qb, $userId);

        return $qb->getQuery()->getResult();
    }

    // public function create($data)
    // {
    //     $user = new User();
    //     $user->setEmail($data->email);
    //     $user->setUsername($data->username);
    //     $user->setProfilePicture($data->profilePicture);
    //     $user->setContribution($data->contribution);
    //     $user->setCreatedAt($data->createdAt);
    //     $user->setPlatform($data->platform ?? null);
    //     $user->setVersion($data->version ?? 0);
    //     $user->setLang($data->lang ?? null);
    //     $user->setDisabledConversationNotifications(['']);
    //     $user->setLoggedOut(true);
    //     $user->setNewsletterSubscribed(true);
    //     // $user->setLastConnection(new DateTime());
    //     $password = $this->passwordHasher->hashPassword($user, $data->password);
    //     $user->setPassword($password);

    //     $this->_em->persist($user);
    //     $this->_em->flush();

    //     return $user;
    // }

    public function getUsersInvolvedInSession($sessionId): array
    {
        return $this->createQueryBuilder('u')
            ->join('App\Entity\CommentSession', 'c')
            ->where('c.session = :sessionId')
            ->join('App\Entity\Session', 's')
            ->andWhere('u member of s.participants OR c.addedBy = u.id')
            ->andWhere('s.id = :sessionId')
            ->setParameter(':sessionId', $sessionId)
            ->getQuery()->getResult();
    }

    public function getUserAmountInArea($mapNorthEastLat, $mapNorthEastLng, $mapSouthWestLat, $mapSouthWestLng): int
    {
        $qb = $this->createQueryBuilder('u');

        $qb->select('COUNT(u.id) AS amount');
        $qb->Where('u.lastLocationLat <= :lat1')
            ->andWhere('u.lastLocationLon <= :lon1')
            ->andWhere('u.lastLocationLat >= :lat2')
            ->andWhere('u.lastLocationLon >= :lon2');
        $qb->setParameter(':lon1', $mapNorthEastLng)
            ->setParameter(':lat1', $mapNorthEastLat)
            ->setParameter(':lon2', $mapSouthWestLng)
            ->setParameter(':lat2', $mapSouthWestLat);

        return $qb->getQuery()->getResult()[0]["amount"];
    }

    public function getUsersWithoutRefreshToken(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('App\Entity\RefreshToken', 'r', Expr\Join::WITH, 'r.username = u.username')
            ->where('r.username IS NULL')
            ->getQuery()->getResult();
    }
}

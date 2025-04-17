<?php

namespace App\Repository;

use App\Entity\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Conversation>
 *
 * @method Conversation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Conversation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Conversation[]    findAll()
 * @method Conversation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(
        ManagerRegistry $registry,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Conversation::class);
        $this->paginator = $paginator;
    }

    // has to be used everywhere when getting informations about conversations
    public function onlyIfUserIsInConversation(QueryBuilder $qb, int $userId): QueryBuilder
    {
        $qb->andWhere('c.administrator = :userId OR c.recipient = :userId OR :userId MEMBER OF c.members')
            ->setParameter(':userId', $userId);

        return $qb;
    }


    public function onlyIfUserIsNotInGroup(QueryBuilder $qb, int $userId): QueryBuilder
    {
        $qb->andWhere(':userId NOT MEMBER OF c.members')
            ->setParameter(':userId', $userId);

        return $qb;
    }

    public function isUserInConversation(int $userId, int $conversationId): ?Conversation
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.id = :conversationId')
            ->setParameter(':conversationId', $conversationId);

        $this->onlyIfUserIsInConversation($qb, $userId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getConversations($userId, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.lastMessage', 'DESC');

        $this->onlyIfUserIsInConversation($qb, $userId);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function doesConversationExist($userIds, $requesterId, $name): ?Conversation
    {
        $qb = $this->createQueryBuilder('c');

        if ($name) {
            $qb->where('c.name = :name')
                ->setParameter(':name', $name);
        } else {
            $qb->where('c.name IS NULL');
        }

        if (count($userIds) > 1) {
            foreach ($userIds as $i => $user) {
                $qb->andWhere(':user' . $i . ' MEMBER OF c.members OR c.administrator = :user' . $i)
                    ->setParameter(':user' . $i, $user);
            }
        } else {
            $qb->andWhere('c.recipient = :user1 OR c.administrator = :user1')
                ->setParameter(':user1', $userIds[0]);
        }
        $this->onlyIfUserIsInConversation($qb, $requesterId);
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getConversation($userId, $conversationId): ?Conversation
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.id = :conversationId')
            ->setParameter(':conversationId', $conversationId);

        $this->onlyIfUserIsInConversation($qb, $userId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findConversations($userId, $name, $onlyIfMember, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.name LIKE :name')
            ->orderBy('c.name')
            ->setParameter(':name', '%' . $name . '%');

        if ($onlyIfMember !== 'false') {
            $this->onlyIfUserIsInConversation($qb, $userId);
        }

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function getConversationDetail($userId, $conversationId): ?array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.id = :conversationId')
            ->setParameter(':conversationId', $conversationId)
            ->select('c as conversation')
            ->addSelect('(CASE WHEN (c.administrator = :userId OR c.recipient = :userId OR :userId MEMBER OF c.members) THEN \'member\' WHEN (:userId MEMBER OF c.awaitingMembers) THEN \'requested\' ELSE \'none\' END) as membership')
            ->setParameter(':userId', $userId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getSuggestedConversaions($user, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.name IS NOT NULL')
            ->andWhere('c.private = 0')
            ->select('c as conversation')
            //calcul de distance exacte (en miles squared)
            // ->addSelect('1/(POWER(69.1 * (c.lat - :userLat), 2) + POWER(69.1 * (:userLon - c.lon) * COS(c.lat / 57.3), 2)) as proximity')
            //calcul de proximité approximative (plus léger)
            ->addSelect('1/(POWER(c.lat - :userLat,2) + POWER(c.lon - :userLon,2)) as proximity')
            ->addSelect('(CASE WHEN (c.sport IN (:favSports)) THEN 1 ELSE 0 END) as favSport')
            ->setParameter('userLat', $user->getLastLocationLat())
            ->setParameter('userLon', $user->getLastLocationLon())
            ->setParameter('favSports', $user->getFavoriteSports())
            ->orderBy('proximity', 'DESC')
            ->addOrderBy('favSport', 'DESC');

        $this->onlyIfUserIsNotInGroup($qb, $user->getId());

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function add(Conversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Conversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

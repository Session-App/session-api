<?php

namespace App\Repository;

use App\Entity\Friendship;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @method Friendship|null find($id, $lockMode = null, $lockVersion = null)
 * @method Friendship|null findOneBy(array $criteria, array $orderBy = null)
 * @method Friendship[]    findAll()
 * @method Friendship[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FriendshipRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(
        ManagerRegistry $registry,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Friendship::class);
        $this->paginator = $paginator;
    }

    public function doesFriendshipExist($user1Id, $user2Id): ?Friendship
    {
        return $this->createQueryBuilder('f')
            ->where('(f.requester = :user1Id AND f.requested = :user2Id) OR (f.requested = :user1Id AND f.requester = :user2Id)')
            ->setParameter(':user1Id', $user1Id)
            ->setParameter(':user2Id', $user2Id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isFriend($foreignUserId, $userId)
    {
        $friendship = $this->createQueryBuilder('f')
            //->select('(CASE WHEN f.acceptedAt IS NOT NULL THEN TRUE ELSE FALSE END) as friendship')
            ->where('(f.requester = :user1Id AND f.requested = :user2Id) OR (f.requested = :user1Id AND f.requester = :user2Id)')
            ->setParameter(':user1Id', $foreignUserId)
            ->setParameter(':user2Id', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($friendship == null) {
            return false;
        } elseif ($friendship->getAcceptedAt() == null) {
            if ($friendship->getRequester()->getId() == $userId) {
                return 'pending';
            } elseif ($friendship->getRequested()->getId() == $userId) {
                return 'receivedRequest';
            }
        } else {
            return true;
        }
    }



    public function getFriendships(int $userId, $onlyFriends): ?array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('(CASE WHEN f.requester = :userId THEN requested.username ELSE requester.username END) as username')
            ->addSelect('(CASE WHEN f.acceptedAt IS NOT NULL THEN true ELSE false END) as friend')
            ->addSelect('(CASE WHEN f.requester = :userId THEN :sent ELSE :received END) as type')
            ->setParameter(':sent', "sent")
            ->setParameter(':received', "received")
            ->addSelect('(CASE WHEN f.requester = :userId THEN requested.id ELSE requester.id END) as id')
            ->addSelect('(CASE WHEN f.requester = :userId THEN requested.profilePicture ELSE requester.profilePicture END) as profilePicture')
            ->join('App\Entity\User', 'requester', Join::WITH, 'requester = f.requester')
            ->join('App\Entity\User', 'requested', Join::WITH, 'requested = f.requested')
            ->where('f.requester = :userId OR f.requested = :userId')
            ->addOrderBy('username', 'ASC')
            ->setParameter(':userId', $userId);

        if ($onlyFriends == "true") {
            $qb->andWhere('f.acceptedAt IS NOT NULL');
        }

        return $qb->getQuery()->getResult();


        /*
        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
         */
    }
}

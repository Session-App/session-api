<?php

namespace App\Repository;

use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @method Session|null find($id, $lockMode = null, $lockVersion = null)
 * @method Session|null findOneBy(array $criteria, array $orderBy = null)
 * @method Session[]    findAll()
 * @method Session[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SessionRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(
        ManagerRegistry $registry,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Session::class);
        $this->paginator = $paginator;
    }

    public function getSpotSessionsInPeriod($spotId, $periodBegin, $periodEnd): ?array
    {
        $qb = $this->createQueryBuilder('se')
            ->join('se.spot', 'sp')
            ->Where('sp.id = :spotId')
            ->andWhere('DATE(se.date) >= :periodBegin')
            ->orderBy('se.date', 'ASC');

        if ($periodEnd != 'null') {
            $qb->andWhere('DATE(se.date) <= :periodEnd')
                ->setParameter(':periodEnd', $periodEnd);
        }

        $qb->setParameter(':spotId', $spotId)
            ->setParameter(':periodBegin', $periodBegin);
        return $qb->getQuery()->getResult();
    }

    public function amountOfPassedSessions($userId, $now): ?int
    {
        return $this->createQueryBuilder('s')
            ->select('count(s)')
            // ->leftJoin('s.participants', 'p')
            ->Where('s.createdBy = :userId OR :userId MEMBER OF s.participants')
            ->andWhere('s.date < :now')
            ->setParameter(':now', $now)
            ->setParameter(':userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function userPassedSessions($userId, $now, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s')
            // ->leftJoin('s.participants', 'p')
            ->Where('s.createdBy = :userId OR :userId MEMBER OF s.participants')
            ->andWhere('s.date < :now')
            ->orderBy('s.date', 'DESC')
            ->setParameter(':now', $now)
            ->setParameter(':userId', $userId);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    /*

    public function amountOfJoinedSessions($userId, $now): ?int
    {
    return $this->createQueryBuilder('s')
                ->select('count(s)')
                ->leftJoin('s.participants', 'participants')
                ->Where('participants.id = :userId')
                ->andWhere('s.date < :now')
                ->setParameter(':now', $now)
                ->setParameter(':userId', $userId)
                ->getQuery()
                ->getSingleScalarResult();
    }

    public function userJoinedSessions($userId, $now, $page, $size): ?PaginationInterface
    {
    $qb = $this->createQueryBuilder('s')
                ->select('s')
                ->leftJoin('s.participants', 'p')
                ->Where('p.id = :userId')
                ->andWhere('s.date < :now')
                ->orderBy('s.date', 'DESC')
                ->setParameter(':now', $now)
                ->setParameter(':userId', $userId);

    return $this->paginator->paginate(
        $qb->getQuery(),
        $page,
        $size
    );
    }
     */

    public function amountOfUpcomingSessions($userId, $now): ?int
    {
        return $this->createQueryBuilder('s')
            ->select('count(s.id)')
            // ->leftJoin('s.participants', 'p')
            ->Where('s.createdBy = :userId OR :userId MEMBER OF s.participants')
            ->andWhere('s.date >= :now')
            ->setParameter(':userId', $userId)
            ->setParameter(':now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function userUpcomingSessions($userId, $now, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s')
            // ->leftJoin('s.participants', 'p')
            ->Where('s.createdBy = :userId OR :userId MEMBER OF s.participants')
            ->andWhere('s.date >= :now')
            ->orderBy('s.date', 'ASC')
            ->setParameter(':userId', $userId)
            ->setParameter(':now', $now);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function upcomingSessionsInConversation($userId, $now, $conversationId): array
    {
        return $this->createQueryBuilder('s')
            ->join('App\Entity\Message', 'm')
            ->join('App\Entity\Conversation', 'c')
            ->where('c.administrator = :userId OR c.recipient = :userId OR :userId MEMBER OF c.members')
            ->andwhere('m.session = s.id')
            ->andWhere('m.conversation = :conversationId')
            ->andWhere('s.date > :now')
            ->setParameter(':conversationId', $conversationId)
            ->setParameter(':now', $now)
            ->setParameter(':userId', $userId)
            ->getQuery()->getResult();
    }
}

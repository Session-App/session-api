<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(
        ManagerRegistry $registry,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Message::class);
        $this->paginator = $paginator;
    }

    // has to be used everywhere when getting messages
    public function onlyIfUserIsInConversation(QueryBuilder $qb, int $userId): QueryBuilder
    {
        $qb->join('m.conversation', 'c')
            ->andWhere('c.administrator = :userId OR c.recipient = :userId OR :userId MEMBER OF c.members')
            ->setParameter(':userId', $userId);

        return $qb;
    }

    public function getMessagesFromConversation($conversationId, $userId, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.conversation = :conversationId')
            ->orderBy('m.sentAt', 'DESC')
            ->setParameter(':conversationId', $conversationId);

        $this->onlyIfUserIsInConversation($qb, $userId);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function getUnseenMessages(int $userId, int $lastMessageId): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.id > :lastMessageId')
            ->orderBy('m.sentAt', 'ASC')
            ->setParameter(':lastMessageId', $lastMessageId);

        $this->onlyIfUserIsInConversation($qb, $userId);
        // dd($qb->getQuery());

        return $qb->getQuery()->getResult();
    }

    public function add(Message $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Message $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return Message[] Returns an array of Message objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

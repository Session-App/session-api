<?php

namespace App\Repository;

use App\Entity\CommentSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CommentSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommentSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommentSession[]    findAll()
 * @method CommentSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentSession::class);
    }
}

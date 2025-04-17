<?php

namespace App\Repository;

use App\Entity\CommentSpot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CommentSpot|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommentSpot|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommentSpot[]    findAll()
 * @method CommentSpot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentSpotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentSpot::class);
    }
}

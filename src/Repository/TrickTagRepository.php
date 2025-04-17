<?php

namespace App\Repository;

use App\Entity\TrickTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrickTag>
 *
 * @method TrickTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrickTag|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrickTag[]    findAll()
 * @method TrickTag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrickTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrickTag::class);
    }

    public function save(TrickTag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TrickTag $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getTags(int $sportId): array
    {
        return $this->createQueryBuilder('ta')
            ->join('ta.tricks', 'tr')
            ->where(':sportId = tr.sport')
            ->select('ta.id')
            ->addSelect('CAST(SUM(CASE WHEN tr.variationOf IS NULL then 1 else 0 end) as UNSIGNED) as tricksAmount')
            ->addSelect('COUNT(tr.variationOf) as variationsAmount')
            ->groupBy('ta.id')
            ->setParameter('sportId', $sportId)
            ->getQuery()->getResult();
    }

    //    /**
    //     * @return TrickTag[] Returns an array of TrickTag objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TrickTag
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

<?php

namespace App\Repository;

use App\Entity\Trick;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\TranslatableListener;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @extends ServiceEntityRepository<Trick>
 *
 * @method Trick|null find($id, $lockMode = null, $lockVersion = null)
 * @method Trick|null findOneBy(array $criteria, array $orderBy = null)
 * @method Trick[]    findAll()
 * @method Trick[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrickRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Trick::class);
        $this->paginator = $paginator;
    }

    public function remove(Trick $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function translateResults(Query $query, string $locale): void
    {
        $query = $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale)
            ->setHint(
                \Doctrine\ORM\Query::HINT_CUSTOM_OUTPUT_WALKER,
                'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
            );
    }

    public function getTricks(int $sportId = 0, int $tagId = 0, int $page, int $size, string $name = '', $locale, $showVariations = false): PaginationInterface
    {
        $qb = $this->createQueryBuilder('t');
        if ($sportId !== 0) {
            $qb->andWhere('t.sport = :sportId')
                ->setParameter('sportId', $sportId);
        }
        if ($tagId !== 0) {
            $qb->andWhere(':tag MEMBER OF t.tags')
                ->setParameter('tag', $tagId);
        }
        if ($name !== '') {
            $qb->setParameter('name', '%' . $name . '%')
                ->andWhere('t.name LIKE :name');
        }
        if (!$showVariations) {
            $qb->andWhere('t.variationOf IS NULL');
        }

        $query = $qb->orderBy('t.points')->getQuery();
        $this->translateResults($query, $locale);

        return $this->paginator->paginate(
            $query,
            $page,
            $size
        );
    }

    public function getTrick(int $trickId, string $locale): ?Trick
    {
        $query = $this->createQueryBuilder('t')
            ->andWhere('t.id = :trickId')
            ->setParameter('trickId', $trickId)
            ->getQuery();
        $this->translateResults($query, $locale);
        return $query->getOneOrNullResult();
    }

    public function getTrickVariationsAmount(int $trickId): int
    {
        $query = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.variationOf = :trickId')
            ->setParameter('trickId', $trickId)
            ->getQuery();
        return $query->getOneOrNullResult()["1"];
    }

    public function getVariations(int $trickId, string $locale): array
    {
        $query = $this->createQueryBuilder('t')
            ->andWhere('t.variationOf = :trickId')
            ->setParameter('trickId', $trickId)
            ->getQuery();
        $this->translateResults($query, $locale);
        return $query->getResult();
    }

    public function findSimilarTrick(string $name, int $sportId, ?string $video): ?Trick
    {
        //todo : check if same video
        return $this->createQueryBuilder('t')
            ->andWhere('t.name LIKE :name')
            ->andWhere('t.sport = :sportId')
            ->setParameter('name', '%' . $name . '%')
            ->setParameter('sportId', $sportId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

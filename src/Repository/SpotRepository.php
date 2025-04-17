<?php

namespace App\Repository;

use App\Entity\Spot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @method Spot|null find($id, $lockMode = null, $lockVersion = null)
 * @method Spot|null findOneBy(array $criteria, array $orderBy = null)
 * @method Spot[]    findAll()
 * @method Spot[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpotRepository extends ServiceEntityRepository
{
    private PaginatorInterface $paginator;

    public function __construct(
        ManagerRegistry $registry,
        PaginatorInterface $paginator
    ) {
        parent::__construct($registry, Spot::class);
        $this->paginator = $paginator;
    }

    public function findSpotsInArea($requestQuery, $sportId, $isVerified): ?array
    {
        $mapNorthEastLat = $requestQuery->get('mapNorthEastLat');
        $mapNorthEastLng = $requestQuery->get('mapNorthEastLng');
        $mapSouthWestLat = $requestQuery->get('mapSouthWestLat');
        $mapSouthWestLng = $requestQuery->get('mapSouthWestLng');

        $qb = $this->createQueryBuilder('sp');
        $qb->Where('sp.lat <= :lat1')
            ->andWhere('sp.lon <= :lon1')
            ->andWhere('sp.lat >= :lat2')
            ->andWhere('sp.lon >= :lon2')
            ->andWhere('sp.sport = :sportId')
            ->andWhere('sp.validated = true');

        if (!$isVerified) {
            $qb->andWhere('sp.secret = :false')
                ->setParameter(':false', false);
        }

        $qb->setParameter(':lon1', $mapNorthEastLng)
            ->setParameter(':lat1', $mapNorthEastLat)
            ->setParameter(':lon2', $mapSouthWestLng)
            ->setParameter(':lat2', $mapSouthWestLat)
            ->setParameter(':sportId', $sportId);

        return $qb->getQuery()->getResult();
    }

    // todo : ne faire qu'une seule fonction et envoyer une liste d'un seul sport quand on cherche juste pour 1 sport et pas les sports favoris : retirer la fonction du dessus
    public function findSpotsInAreaFavoriteSports($requestQuery, $favoriteSports): ?array
    {
        $mapNorthEastLat = $requestQuery->get('mapNorthEastLat');
        $mapNorthEastLng = $requestQuery->get('mapNorthEastLng');
        $mapSouthWestLat = $requestQuery->get('mapSouthWestLat');
        $mapSouthWestLng = $requestQuery->get('mapSouthWestLng');

        $qb = $this->createQueryBuilder('sp');
        $qb->Where('sp.lat <= :lat1')
            ->andWhere('sp.lon <= :lon1')
            ->andWhere('sp.lat >= :lat2')
            ->andWhere('sp.lon >= :lon2')
            ->andWhere('sp.validated = true');

        $favSportsRequestPart = "";
        foreach ($favoriteSports as $i => $sport) {
            $favSportsRequestPart .= "sp.sport = :sportId" . $i;
            if (!$sport[1]) {
                $favSportsRequestPart .= " AND sp.secret = :secret" . $i;
                $qb->setParameter(':secret' . $i, $sport[1]);
            }
            $favSportsRequestPart .= " OR ";
            $qb->setParameter(':sportId' . $i, $sport[0]);
        }

        $qb->andWhere(substr($favSportsRequestPart, 0, -3));

        $qb->setParameter(':lon1', $mapNorthEastLng)
            ->setParameter(':lat1', $mapNorthEastLat)
            ->setParameter(':lon2', $mapSouthWestLng)
            ->setParameter(':lat2', $mapSouthWestLat);

        return $qb->getQuery()->getResult();
    }

    public function findClosestSpots($lat, $lon, $sports, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('sp');
        $qb->where('sp.validated = true');

        $sportsRequestPart = "";
        foreach ($sports as $i => $sport) {
            $sportsRequestPart .= "sp.sport = :sportId" . $i;
            if (!$sport[1]) {
                $sportsRequestPart .= " AND sp.secret = :secret" . $i;
                $qb->setParameter(':secret' . $i, $sport[1]);
            }
            $sportsRequestPart .= " OR ";
            $qb->setParameter(':sportId' . $i, $sport[0]);
        }
        $qb->andWhere(substr($sportsRequestPart, 0, -3));

        // ordering by distance, the distance is in miles squared
        $qb->orderBy('POWER(69.1 * (sp.lat - :userLat), 2) + POWER(69.1 * (:userLon - sp.lon) * COS(sp.lat / 57.3), 2)');
        $qb->setParameter(':userLat', $lat);
        $qb->setParameter(':userLon', $lon);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function findPossibleDuplicates($lat, $lon, $sport, $distance = 0.5 /* 300m */): ?array
    {
        return $this->createQueryBuilder('sp')
            ->where('sp.validated = true')
            ->andWhere('POWER(69.1 * (sp.lat - :lat), 2) + POWER(69.1 * (:lon - sp.lon) * COS(sp.lat / 57.3), 2) < :distanceSquared')
            ->andWhere('sp.sport = :sportId')
            ->setParameter(':lat', $lat)
            ->setParameter(':lon', $lon)
            ->setParameter(':sportId', $sport)
            ->setParameter(':distanceSquared', pow($distance, 2))
            ->getQuery()->getResult();
    }

    public function getUnvalidatedSpots($adminSports, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s')
            ->andWhere('s.validated = false');

        if ($adminSports !== 'all') {
            $sportsQueryPart = '';
            foreach ($adminSports as $i => $sport) {
                $sportsQueryPart .= 's.sport = :sport' . $i . ' OR ';
                $qb->setParameter(':sport' . $i, $sport);
            }
            $sportsQueryPart = substr($sportsQueryPart, 0, -3);
            $qb->andWhere($sportsQueryPart);
        }
        $qb->orderBy('s.createdAt', 'ASC');

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function getSpotsWithUnvalidatedPictures($adminSports, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s')
            ->join('s.pictures', 'p')
            ->andWhere('p.validated = false');

        if ($adminSports !== 'all') {
            $sportsQueryPart = '';
            foreach ($adminSports as $i => $sport) {
                $sportsQueryPart .= 's.sport = :sport' . $i . ' OR ';
                $qb->setParameter(':sport' . $i, $sport);
            }
            $sportsQueryPart = substr($sportsQueryPart, 0, -3);
            $qb->andWhere($sportsQueryPart);
        }

        $qb->orderBy('s.createdAt', 'ASC');

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    // public function findSpotsWithSessions($sportId, $periodBegin, $periodEnd, $isVerified): ?array
    // {
    //     $qb = $this->createQueryBuilder('sp')
    //         ->Where('sp.sport = :sportId')
    //         ->setParameter(':sportId', $sportId)
    //         ->join('sp.sessions', 's');

    //     if ($periodEnd !== 'null') {
    //         $qb->andWhere('s.date BETWEEN :periodBegin AND :periodEnd')
    //             ->setParameter(':periodEnd', $periodEnd);
    //     } else {
    //         $qb->andWhere('s.date >= :periodBegin');
    //     }

    //     if (!$isVerified) {
    //         $qb->andWhere('sp.secret = :false')
    //             ->setParameter(':false', false);
    //     }

    //     $qb->setParameter(':periodBegin', $periodBegin);
    //     return $qb->getQuery()->getResult();
    // }

    public function findSpotsWithSessionsAllSports($periodBegin): ?array
    {
        return $this->createQueryBuilder('sp')
            ->join('sp.sessions', 's')
            ->andWhere('DATE(s.date) >= :periodBegin')
            ->setParameter(':periodBegin', $periodBegin)
            ->getQuery()->getResult();
    }

    public function findSpotsWithSessions($sports, $periodBegin, $periodEnd): ?array
    {
        $qb = $this->createQueryBuilder('sp')
            ->join('sp.sessions', 's');

        if ($periodEnd !== 'null') {
            $qb->andWhere('DATE(s.date) BETWEEN :periodBegin AND :periodEnd')
                ->setParameter(':periodEnd', $periodEnd);
        } else {
            $qb->andWhere('DATE(s.date) >= :periodBegin');
        }

        $sportsRequestPart = "";
        foreach ($sports as $i => $sport) {
            $sportsRequestPart .= "sp.sport = :sportId" . $i;
            if (!$sport[1]) {
                $sportsRequestPart .= " AND sp.secret = :secret" . $i;
                $qb->setParameter(':secret' . $i, $sport[1]);
            }
            $sportsRequestPart .= " OR ";
            $qb->setParameter(':sportId' . $i, $sport[0]);
        }
        $qb->andWhere(substr($sportsRequestPart, 0, -3));

        $qb->setParameter(':periodBegin', $periodBegin);
        return $qb->getQuery()->getResult();
    }

    public function amountOfAddedSpots($userId): ?int
    {
        return $this->createQueryBuilder('s')
            ->select('count(s)')
            ->Where('s.addedBy = :userId')
            ->andWhere('s.validated = true')
            ->setParameter(':userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function userAddedSpots($userId, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s')
            ->Where('s.addedBy = :userId')
            ->andWhere('s.validated = true')
            ->orderBy('s.createdAt', 'DESC')
            ->setParameter(':userId', $userId);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    public function userFavoriteSpots($userId, $page, $size): ?PaginationInterface
    {
        $qb = $this->createQueryBuilder('s')
            ->join('App\Entity\User', 'u')
            ->where('s MEMBER OF u.favoriteSpots')
            //->AddSelect('u.id')
            //->where('u.favoriteSpots = s.id')
            ->andWhere('u.id = :userId')
            ->setParameter(':userId', $userId);

        return $this->paginator->paginate(
            $qb->getQuery(),
            $page,
            $size
        );
    }

    /*
    public function isSpotFavorite($userId, $spotId): bool
    {
    $spot = $this->createQueryBuilder('s')
               ->join('App\Entity\User', 'u')
               ->where('s MEMBER OF u.favoriteSpots')
               ->andWhere('u.id = :userId')
               ->andWhere('s.id = :spotId')
               ->setParameter('spotId', $spotId)
               ->setParameter(':userId', $userId)
               ->getQuery()
               ->getOneOrNullResult();
    return $spot !== null;
    }
     */
}

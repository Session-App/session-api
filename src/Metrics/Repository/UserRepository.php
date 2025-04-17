<?php

namespace App\Metrics\Repository;

use App\Entity\Connection;
use App\Entity\Message;
use App\Entity\User;

class UserRepository extends MetricsRepository
{
    public function accountsCreatedPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(u.id) AS amount')->from(User::class, 'u')
            ->addSelect('DATE(u.createdAt) AS day')
            ->where('DATE(u.createdAt) BETWEEN :from AND :to')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to);
        $qb->groupBy('day');
        return $qb->getQuery()->getResult();
    }

    public function usersConnectedPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')->from(Connection::class, 'c')
            ->join('c.user', 'u')
            ->select('u.username')
            ->addSelect('u.createdAt')
            ->addSelect('u.username')
            ->addSelect('u.platform')
            ->addSelect('u.lang')
            ->addSelect('DATE(c.date) AS lastConnection')
            ->where('DATE(c.date) BETWEEN :from AND :to')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to);
        // $qb->groupBy('u');
        $qb->orderBy('lastConnection', 'DESC');
        return $qb->getQuery()->getResult();
    }

    public function favoriteSportsPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(u.id) AS amount')->from(User::class, 'u')
            ->addSelect('DATE(u.createdAt) AS day')
            ->where('DATE(u.createdAt) BETWEEN :from AND :to')
            ->andWhere('u.favoriteSports IS NOT NULL')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to);
        $qb->groupBy('day');
        return $qb->getQuery()->getResult();
    }

    public function favoriteSportsTotal()
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(u.id) AS amount')->from(User::class, 'u')
            ->andWhere('u.favoriteSports IS NOT NULL');
        return $qb->getQuery()->getResult();
    }

    public function activeUsersPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()->select('COUNT(DISTINCT c.user) AS amount')->from(Connection::class, 'c')
            ->addSelect('DATE(c.date) AS day')
            ->where('DATE(c.date) BETWEEN :from AND :to')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to);
        $qb->groupBy('day');
        return $qb->getQuery()->getResult();
    }

    public function accountsCreatedTotal()
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(u.id) AS amount')
            ->from(User::class, 'u')
            ->getQuery()->getResult();
    }

    public function usersAcceptedLocationTotal()
    {
        return $this->em->createQueryBuilder()
            ->select('count(u.id) as amount')
            ->where('u.lastLocationLon IS NOT NULL')
            ->from(User::class, 'u')
            ->getQuery()->getResult();
    }

    public function usersAcceptedNotificationsTotal()
    {
        return $this->em->createQueryBuilder()
            ->select('count(u.id) as amount')
            ->where('u.pushNotificationsToken IS NOT NULL')
            ->from(User::class, 'u')
            ->getQuery()->getResult();
    }

    public function usersWithBioTotal()
    {
        return $this->em->createQueryBuilder()
            ->select('count(u.id) as amount')
            ->where('u.bio IS NOT NULL')
            ->from(User::class, 'u')
            ->getQuery()->getResult();
    }

    public function usersWithProfilePictureTotal()
    {
        return $this->em->createQueryBuilder()
            ->select('count(u.id) as amount')
            ->where('u.profilePicture != \'pp-default.svg\'')
            ->from(User::class, 'u')
            ->getQuery()->getResult();
    }

    public function messagesSent($from, $to)
    {
        $qb = $this->em->createQueryBuilder()->select('count(m.id) as amount')->from(Message::class, 'm')
            ->addSelect('DATE(m.sentAt) as day')
            ->where('DATE(m.sentAt) BETWEEN :from AND :to')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to);
        $qb->groupBy('day');
        return $qb->getQuery()->getResult();
    }

    public function favoriteSportsChosen($locale = 'fr_FR')
    {
        $qb = $this->em->createQueryBuilder();
        $countQueryPart = '';
        for ($i = 1; $i < 78; $i++) {
            $countQueryPart .= 'SUM(CASE WHEN FIND_IN_SET(:sportId' . $i . ', u.favoriteSports) != 0 then 1 else 0 end) as ';
            if ($this->translator->trans('sports.' . $i, [], 'messages', $locale) !== 'sports.' . $i) {
                $countQueryPart .= $this->translator->trans('sports.' . $i, [], 'messages', $locale);
            } else {
                $countQueryPart .= 'sport' . $i;
            }
            $countQueryPart .= ',';
            $qb->setParameter(':sportId' . $i, $i);
        }
        $qb->addSelect(substr($countQueryPart, 0, -1))
            ->from(User::class, 'u');
        // dd($qb->getQuery());
        return $qb->getQuery()->getResult();
    }

    public function userLocations()
    {
        return $this->em->createQueryBuilder()
            ->select('u.lastLocationLat as lat')
            ->addSelect('u.lastLocationLon as lon')
            ->addSelect('u.username as name')
            ->from(User::class, 'u')
            ->where('u.lastLocationLat IS NOT NULL')
            ->getQuery()->getResult();
    }

    public function platforms()
    {
        return $this->em->createQueryBuilder()
            ->select('count(u.id) AS amount')
            ->addSelect('CASE WHEN u.platform IS null THEN \'unknown\' ELSE u.platform END as name')
            ->addSelect('u.version')
            ->from(User::class, 'u')
            ->groupBy('u.platform')
            ->addGroupBy('u.version')
            ->getQuery()->getResult();
    }
}

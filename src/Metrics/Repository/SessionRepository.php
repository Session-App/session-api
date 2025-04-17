<?php

namespace App\Metrics\Repository;

use App\Entity\Session;

class SessionRepository extends MetricsRepository
{
    public function sessionsCreatedPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()->select('count(s.id) as amount')->from(Session::class, 's')
            ->addSelect('DATE(s.createdAt) as day')
            ->where('DATE(s.createdAt) BETWEEN :from AND :to')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to)
            ->groupBy('day');
        return $qb->getQuery()->getResult();
    }

    public function sessionsCreatedTotal()
    {
        $qb = $this->em->createQueryBuilder()->select('count(s.id) as amount')->from(Session::class, 's');
        return $qb->getQuery()->getResult();
    }

    public function participantsPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Session::class, 's')
            ->select('count(p) as amount')
            ->join('s.participants', 'p')
            // very weird I had to switch to and from in order for it to return results
            ->where('DATE(s.createdAt) BETWEEN :to AND :from')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to)
            ->addSelect('DATE(s.createdAt) as day')
            ->groupBy('day');

        return $qb->getQuery()->getResult();
    }

    public function participantsTotal()
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Session::class, 's')
            ->select('count(p) as amount')
            ->join('s.participants', 'p');

        return $qb->getQuery()->getResult();
    }
}

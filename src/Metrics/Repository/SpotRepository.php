<?php

namespace App\Metrics\Repository;

use App\Entity\Spot;

class SpotRepository extends MetricsRepository
{
    public function spotsAddedPeriod($from, $to)
    {
        $qb = $this->em->createQueryBuilder()->select('count(s.id) as amount')->from(Spot::class, 's')
            ->addSelect('DATE(s.createdAt) as day')
            ->where('DATE(s.createdAt) BETWEEN :from AND :to')
            ->setParameter(':from', $from)
            ->setParameter(':to', $to);
        $qb->groupBy('day');
        return $qb->getQuery()->getResult();
    }
}

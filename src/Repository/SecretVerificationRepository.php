<?php

namespace App\Repository;

use App\Entity\SecretVerification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SecretVerification|null find($id, $lockMode = null, $lockVersion = null)
 * @method SecretVerification|null findOneBy(array $criteria, array $orderBy = null)
 * @method SecretVerification[]    findAll()
 * @method SecretVerification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SecretVerificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecretVerification::class);
    }

    public function getVerifiedAmount($userId, $sportId): int
    {
        return $this->createQueryBuilder('v')
                    ->select('count(v)')
                    ->Where('v.verified = :userId')
                    ->andWhere('v.sport = :sportId')
                    ->setParameter(':userId', $userId)
                    ->setParameter(':sportId', $sportId)
                    ->getQuery()
                    ->getSingleScalarResult();
    }

    public function verificationExists($toBeVerifiedUser, $verifier, $sportId): int
    {
        return $this->createQueryBuilder('v')
                    ->select('count(1)')
                    ->Where('v.verified = :toBeVerifiedUser')
                    ->andWhere('v.verifier = :verifier')
                    ->andWhere('v.sport = :sportId')
                    ->setParameter(':toBeVerifiedUser', $toBeVerifiedUser)
                    ->setParameter(':verifier', $verifier)
                    ->setParameter(':sportId', $sportId)
                    ->getQuery()
                    ->getSingleScalarResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\StepData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StepData|null find($id, $lockMode = null, $lockVersion = null)
 * @method StepData|null findOneBy(array $criteria, array $orderBy = null)
 * @method StepData[]    findAll()
 * @method StepData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StepDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StepData::class);
    }

    // /**
    //  * @return StepData[] Returns an array of StepData objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?StepData
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}

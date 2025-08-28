<?php

namespace App\Repository;

use App\Entity\Caisse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Caisse>
 */
class CaisseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Caisse::class);
    }

    public function getCaissesFiltered(?string $agent, ?string $dateRange): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if ($agent) {
            $qb->andWhere('LOWER(c.agentResponsable) LIKE :agent')
                ->setParameter('agent', '%' . strtolower($agent) . '%');
        }

        if ($dateRange) {
            [$startDate, $endDate] = explode(' to ', str_replace('/', '-', $dateRange));
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $qb->andWhere('c.openedAt BETWEEN :start AND :end')
                ->setParameter('start', $start->setTime(0, 0))
                ->setParameter('end', $end->setTime(23, 59));
        }

        return $qb;
    }

    //    /**
    //     * @return Article[] Returns an array of Article objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Article
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

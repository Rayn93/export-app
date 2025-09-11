<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShopifyAppConfig;
use App\Service\Utils\PasswordEncryptor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopifyAppConfig>
 */
class ShopifyAppConfigRepository extends ServiceEntityRepository
{
    private PasswordEncryptor $passwordEncryptor;

    public function __construct(ManagerRegistry $registry, PasswordEncryptor $passwordEncryptor)
    {
        parent::__construct($registry, ShopifyAppConfig::class);

        $this->passwordEncryptor = $passwordEncryptor;
    }

    public function save(ShopifyAppConfig $entity, bool $flush = false): void
    {
        if (!empty($entity->getFfApiPassword())) {
            $entity->setFfApiPassword($this->passwordEncryptor->encrypt($entity->getFfApiPassword()));
        }

        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return ShopifyAppConfig[] Returns an array of ShopifyAppConfig objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ShopifyAppConfig
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

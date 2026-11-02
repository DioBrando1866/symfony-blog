<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Returns an array of Post objects
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Post[] Returns an array of Post objects
     */
    public function findRecents(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.publishedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findPaginados(int $page, int $perPage): Paginator
    {
        $query = $this->createQueryBuilder('p')
            ->orderBy('p.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query, false);
    }

    /**
     * Página $page de los posts cuyo título o contenido contienen $searchTerm.
     */
    public function findBuscadosPaginados(string $searchTerm, int $page, int $perPage): Paginator
    {
        $query = $this->createQueryBuilder('p')
            ->andWhere('p.title LIKE :val OR p.content LIKE :val')
            ->setParameter('val', '%' . $searchTerm . '%')
            ->orderBy('p.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query, false);
    }

    /**
     * @return Post[] Returns an array of Post objects
     */
    public function findByText(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.title LIKE :val OR p.content LIKE :val')
            ->setParameter('val', '%' . $searchTerm . '%')
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
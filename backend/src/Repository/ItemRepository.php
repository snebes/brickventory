<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * Find items by category
     *
     * @return Item[]
     */
    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('i.itemName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find item by item ID (SKU)
     */
    public function findByItemId(string $itemId): ?Item
    {
        return $this->findOneBy(['itemId' => $itemId]);
    }

    /**
     * Search items by name or ID
     *
     * @return Item[]
     */
    public function searchItems(string $query): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.itemName LIKE :query')
            ->orWhere('i.itemId LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('i.itemName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseOrder>
 */
class PurchaseOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrder::class);
    }

    /**
     * Generate the next order number in the format PO001, PO002, etc.
     * The number is left-padded with zeros to a minimum of 3 digits.
     */
    public function getNextOrderNumber(): string
    {
        $prefix = 'PO';
        $minDigits = 3;

        // Find the highest existing order number
        $result = $this->createQueryBuilder('po')
            ->select('po.orderNumber')
            ->where('po.orderNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('po.orderNumber', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        $maxNumber = 0;
        foreach ($result as $row) {
            $orderNumber = $row['orderNumber'];
            // Extract the numeric part after the prefix
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $orderNumber, $matches)) {
                $number = (int) $matches[1];
                if ($number > $maxNumber) {
                    $maxNumber = $number;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        return $prefix . str_pad((string) $nextNumber, $minDigits, '0', STR_PAD_LEFT);
    }
}

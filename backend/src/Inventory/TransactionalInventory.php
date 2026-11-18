<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use Doctrine\DBAL\Exception\RetryableException;

/** Database retries always start with a fresh Doctrine unit of work. */
trait TransactionalInventory
{
    private function transaction(callable $work): mixed
    {
        $this->em = $this->registry->getManager();
        $nested = $this->em->getConnection()->isTransactionActive();
        for ($attempt = 1;; ++$attempt) {
            try {
                return $this->em->wrapInTransaction($work);
            } catch (RetryableException $error) {
                if ($nested || $attempt >= 3) {
                    throw $error;
                }
                $this->em = $this->registry->resetManager();
                usleep(random_int(1000, 10000));
            }
        }
    }
}

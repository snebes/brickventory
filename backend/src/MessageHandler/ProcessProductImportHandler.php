<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Inventory\ProductImport;
use App\Inventory\InventoryService;
use App\Inventory\ProductCsv;
use App\Inventory\ProductImportRowException;
use App\Inventory\ProductImportStorage;
use App\Message\ProcessProductImport;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessProductImportHandler
{
    public function __construct(private ManagerRegistry $registry, private InventoryService $inventory, private ProductImportStorage $storage, private ProductCsv $csv, private MessageBusInterface $bus)
    {
    }

    public function __invoke(ProcessProductImport $message): void
    {
        try {
            $this->registry->getManager()->wrapInTransaction(function ($em) use ($message) {
                $job = $em->getRepository(ProductImport::class)->createQueryBuilder('j')->where('j.id = :id')->setParameter('id', $message->id)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
                if (!$job || $job->cursor !== $message->cursor || !in_array($job->status, ['queued', 'running', 'retrying'], true)) {
                    return;
                }
                $path = $this->storage->path($job->id);
                $stream = @fopen($path, 'rb');
                if (!$stream) {
                    throw new ProductImportRowException(0, 'Source file is missing or unreadable. Upload it again.');
                }
                try {
                    if (0 !== fseek($stream, $job->cursor)) {
                        throw new \RuntimeException('Unable to seek import source.');
                    }
                    if (0 === $job->cursor) {
                        $header = fgetcsv($stream, 1048577, escape: '');
                        if (!$header) {
                            throw new ProductImportRowException(1, 'CSV is empty.');
                        }
                        $job->header = $this->csv->header($header);
                    }
                    $rows = $numbers = [];
                    while (count($rows) < 500 && ($values = fgetcsv($stream, 1048577, escape: '')) !== false) {
                        ++$job->recordsRead;
                        if ($values === [null]) {
                            continue;
                        }
                        $number = $job->recordsRead + 1;
                        if ($job->processed + count($rows) >= 250000) {
                            throw new ProductImportRowException($number, 'CSV exceeds 250000 product rows. Split it into separate uploads.');
                        }
                        $rows[] = $this->csv->row($job->header, $values, $number);
                        $numbers[] = $number;
                    }
                    if ($rows) {
                        try {
                            $this->inventory->importProducts($rows, false);
                        } catch (ProductImportRowException $error) {
                            throw new ProductImportRowException($numbers[$error->row], $error->getMessage());
                        }
                    }
                    $job->processed += count($rows);
                    $job->cursor = ftell($stream);
                    $job->status = feof($stream) ? 'completed' : 'running';
                    $job->error = [];
                    $job->updatedAt = new \DateTimeImmutable();
                    $em->flush();
                    if ('completed' !== $job->status) {
                        $this->bus->dispatch(new ProcessProductImport($job->id, $job->cursor));
                    }
                } finally {
                    fclose($stream);
                }
            });
        } catch (ProductImportRowException $error) {
            // A bad batch rolls back completely. Earlier checkpoints remain committed.
            if (!$this->registry->getManager()->isOpen()) {
                $this->registry->resetManager();
            }
            $this->registry->getManager()->wrapInTransaction(function ($em) use ($message, $error) {
                $job = $em->getRepository(ProductImport::class)->createQueryBuilder('j')->where('j.id = :id')->setParameter('id', $message->id)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
                if (!$job || $job->cursor !== $message->cursor || !in_array($job->status, ['queued', 'running', 'retrying'], true)) {
                    return;
                }
                $job->status = 'failed';
                $job->error = ['row' => $error->row ?: null, 'message' => $error->getMessage()];
                $job->updatedAt = new \DateTimeImmutable();
            });
        } finally {
            // Bound the identity map independently of the number of products in the file.
            if ($this->registry->getManager()->isOpen()) {
                $this->registry->getManager()->clear();
            }
        }
    }
}

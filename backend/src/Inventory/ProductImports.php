<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\ProductImport;
use App\Message\ProcessProductImport;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ProductImports
{
    public function __construct(private ManagerRegistry $registry, private ProductImportStorage $storage, private MessageBusInterface $bus)
    {
    }

    public function upload(UploadedFile $file): array
    {
        $size = $file->getSize();
        if (!$size || $size > 104857600 || 'csv' !== strtolower($file->getClientOriginalExtension())) {
            throw new UnprocessableEntityHttpException('Upload a nonempty CSV up to 100 MiB.');
        }
        $job = new ProductImport(Uuid::v7()->toRfc4122());
        $job->filename = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
        $job->fileSize = $size;
        $this->storage->store($job->id, $file);
        try {
            $this->registry->getManager()->wrapInTransaction(function ($em) use ($job) {
                $em->persist($job);
                $em->flush();
                // Doctrine transport shares this database transaction: file, job and dispatch are durable before HTTP 202.
                $this->bus->dispatch(new ProcessProductImport($job->id));
            });
        } catch (\Throwable $error) {
            $this->storage->delete($job->id);
            throw $error;
        }

        return $this->view($job);
    }

    public function get(string $id): ProductImport
    {
        return $this->registry->getManager()->find(ProductImport::class, $id) ?? throw new NotFoundHttpException('Import not found.');
    }

    public function listing(int $page): array
    {
        $repo = $this->registry->getManager()->getRepository(ProductImport::class);

        return ['data' => array_map($this->view(...), $repo->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], 20, ($page - 1) * 20)), 'meta' => ['current_page' => $page, 'per_page' => 20, 'total' => $repo->count([])]];
    }

    public function view(ProductImport $job): array
    {
        return ['id' => $job->id, 'filename' => $job->filename, 'status' => $job->status, 'processed' => $job->processed, 'bytes_processed' => $job->cursor, 'bytes_total' => $job->fileSize, 'progress' => min(100, (int) floor(100 * $job->cursor / max(1, $job->fileSize))), 'error' => $job->error ?: null, 'created_at' => $job->createdAt->format(DATE_ATOM), 'updated_at' => $job->updatedAt->format(DATE_ATOM)];
    }

    public function action(string $id, string $action): array
    {
        return $this->registry->getManager()->wrapInTransaction(function ($em) use ($id, $action) {
            $job = $this->get($id);
            $em->refresh($job, LockMode::PESSIMISTIC_WRITE);
            if ('cancel' === $action) {
                if (in_array($job->status, ['completed', 'cancelled'], true)) {
                    return $this->view($job);
                }
                $job->status = 'cancelled';
            } else {
                if (!in_array($job->status, ['failed', 'cancelled'], true)) {
                    throw new UnprocessableEntityHttpException('Only failed or cancelled imports can be resumed.');
                }
                if (!is_file($this->storage->path($id))) {
                    throw new UnprocessableEntityHttpException('Source expired. Upload the file again.');
                }
                $job->status = 'queued';
                $job->error = [];
                $this->bus->dispatch(new ProcessProductImport($id, $job->cursor));
            }
            $job->updatedAt = new \DateTimeImmutable();

            return $this->view($job);
        });
    }

    public function transportFailure(ProcessProductImport $message, bool $retry): void
    {
        if (!$this->registry->getManager()->isOpen()) {
            $this->registry->resetManager();
        }
        $this->registry->getManager()->wrapInTransaction(function ($em) use ($message, $retry) {
            $job = $em->getRepository(ProductImport::class)->createQueryBuilder('j')->where('j.id = :id')->setParameter('id', $message->id)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
            if (!$job || $job->cursor !== $message->cursor || !in_array($job->status, ['queued', 'running', 'retrying'], true)) {
                return;
            }
            $job->status = $retry ? 'retrying' : 'failed';
            $job->error = ['row' => null, 'message' => $retry ? 'Temporary worker failure; retry is scheduled.' : 'Worker failed after retries. Check worker logs, then resume this import.'];
            $job->updatedAt = new \DateTimeImmutable();
        });
    }
}

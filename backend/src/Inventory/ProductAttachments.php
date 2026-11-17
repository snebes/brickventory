<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\Inventory\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class ProductAttachments
{
    public function __construct(private EntityManagerInterface $em, private InventoryService $inventory)
    {
    }

    public function listing(int $productId): array
    {
        $product = $this->inventory->getProduct($productId);

        return array_map($this->view(...), $this->em->getRepository(Attachment::class)->findBy(['product' => $product], ['id' => 'ASC']));
    }

    public function upload(int $productId, UploadedFile $file): array
    {
        $product = $this->inventory->getProduct($productId);

        return $this->em->wrapInTransaction(function () use ($product, $file) {
            $this->em->lock($product, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            if ($this->em->getRepository(Attachment::class)->count(['product' => $product]) >= 20) {
                throw new UnprocessableEntityHttpException('At most 20 attachments per product.');
            }
            $attachment = new Attachment();
            $attachment->product = $product;
            $attachment->name = mb_substr(basename($file->getClientOriginalName()), 0, 255);
            $attachment->mimeType = $file->getMimeType() ?? 'application/octet-stream';
            $attachment->content = $file->getContent();
            $this->em->persist($attachment);
            $this->em->flush();

            return $this->view($attachment);
        });
    }

    public function get(int $productId, int $id): Attachment
    {
        $attachment = $this->em->find(Attachment::class, $id);
        if (!$attachment || $attachment->product->id !== $productId) {
            throw new NotFoundHttpException('Attachment not found.');
        }

        return $attachment;
    }

    public function delete(int $productId, int $id): void
    {
        $this->em->remove($this->get($productId, $id));
        $this->em->flush();
    }

    private function view(Attachment $attachment): array
    {
        return ['id' => $attachment->id, 'product_id' => $attachment->product->id, 'name' => $attachment->name, 'mime_type' => $attachment->mimeType, 'created_at' => $attachment->createdAt->format(DATE_ATOM)];
    }
}

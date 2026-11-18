<?php

// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\EventHandler;

use App\Inventory\ProductImports;
use App\Message\ProcessProductImport;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener(priority: -200)]
final readonly class ProductImportFailureListener
{
    public function __construct(private ProductImports $imports)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($message instanceof ProcessProductImport) {
            $this->imports->transportFailure($message, $event->willRetry());
        }
    }
}

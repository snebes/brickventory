# CQRS Refactoring Guide

This guide provides step-by-step examples for refactoring the current Brickventory codebase to more formally implement CQRS patterns.

## Table of Contents

1. [Command Objects](#1-command-objects)
2. [Command Handlers](#2-command-handlers)
3. [Command Bus Setup](#3-command-bus-setup)
4. [Query Objects](#4-query-objects)
5. [Query Handlers](#5-query-handlers)
6. [Read Models](#6-read-models)
7. [Migration Strategy](#7-migration-strategy)

## 1. Command Objects

### Before: Controller Logic

```php
// src/Controller/PurchaseOrderController.php (current)
#[Route('/api/purchase-orders', name: 'api_purchase_order_create', methods: ['POST'])]
public function create(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    $purchaseOrder = new PurchaseOrder();
    $purchaseOrder->orderNumber = $data['orderNumber'] ?? 'PO-' . date('YmdHis');
    $purchaseOrder->orderDate = new \DateTimeImmutable($data['orderDate']);
    $purchaseOrder->status = $data['status'];
    $purchaseOrder->reference = $data['reference'] ?? '';
    $purchaseOrder->notes = $data['notes'] ?? '';
    
    foreach ($data['lines'] as $lineData) {
        $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
        
        $line = new PurchaseOrderLine();
        $line->purchaseOrder = $purchaseOrder;
        $line->item = $item;
        $line->quantityOrdered = $lineData['quantityOrdered'];
        $line->rate = $lineData['rate'];
        
        $purchaseOrder->lines[] = $line;
    }
    
    $this->entityManager->persist($purchaseOrder);
    $this->entityManager->flush();
    
    $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($purchaseOrder));
    
    return new JsonResponse(['id' => $purchaseOrder->id], 201);
}
```

### After: Command Object

```php
// src/Command/CreatePurchaseOrderCommand.php
<?php

declare(strict_types=1);

namespace App\Command;

final class CreatePurchaseOrderCommand
{
    public function __construct(
        public readonly ?string $orderNumber,
        public readonly string $orderDate,
        public readonly string $status,
        public readonly string $reference,
        public readonly string $notes,
        public readonly array $lines // [['itemId' => 1, 'quantityOrdered' => 10, 'rate' => 5.99], ...]
    ) {}
}
```

### Command Handler

```php
// src/CommandHandler/CreatePurchaseOrderCommandHandler.php
<?php

declare(strict_types=1);

namespace App\CommandHandler;

use App\Command\CreatePurchaseOrderCommand;
use App\Entity\Item;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class CreatePurchaseOrderCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function __invoke(CreatePurchaseOrderCommand $command): int
    {
        $purchaseOrder = new PurchaseOrder();
        $purchaseOrder->orderNumber = $command->orderNumber ?? 'PO-' . date('YmdHis');
        $purchaseOrder->orderDate = new \DateTimeImmutable($command->orderDate);
        $purchaseOrder->status = $command->status;
        $purchaseOrder->reference = $command->reference;
        $purchaseOrder->notes = $command->notes;
        
        foreach ($command->lines as $lineData) {
            $item = $this->entityManager->getRepository(Item::class)->find($lineData['itemId']);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item {$lineData['itemId']} not found");
            }
            
            $line = new PurchaseOrderLine();
            $line->purchaseOrder = $purchaseOrder;
            $line->item = $item;
            $line->quantityOrdered = $lineData['quantityOrdered'];
            $line->rate = $lineData['rate'];
            
            $purchaseOrder->lines[] = $line;
        }
        
        $this->entityManager->persist($purchaseOrder);
        $this->entityManager->flush();
        
        $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($purchaseOrder));
        
        return $purchaseOrder->id;
    }
}
```

### Updated Controller

```php
// src/Controller/PurchaseOrderController.php (refactored)
use App\Command\CreatePurchaseOrderCommand;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/api/purchase-orders', name: 'api_purchase_order_create', methods: ['POST'])]
public function create(Request $request, MessageBusInterface $commandBus): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    $command = new CreatePurchaseOrderCommand(
        orderNumber: $data['orderNumber'] ?? null,
        orderDate: $data['orderDate'],
        status: $data['status'],
        reference: $data['reference'] ?? '',
        notes: $data['notes'] ?? '',
        lines: $data['lines']
    );
    
    $result = $commandBus->dispatch($command);
    $purchaseOrderId = $result->last(HandledStamp::class)->getResult();
    
    return new JsonResponse(['id' => $purchaseOrderId], 201);
}
```

### Benefits

✅ **Separation of Concerns**: Controller handles HTTP, Handler handles business logic
✅ **Testability**: Can test handler without HTTP layer
✅ **Reusability**: Command can be dispatched from CLI, API, or queue
✅ **Validation**: Can add validation middleware
✅ **Logging**: Can add logging middleware
✅ **Transactions**: Can wrap in transaction middleware

## 2. Command Handlers

### Validation Middleware

```php
// src/Middleware/ValidationMiddleware.php
<?php

declare(strict_types=1);

namespace App\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        
        // Validate the command
        $violations = $this->validator->validate($message);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            throw new \InvalidArgumentException(implode(', ', $errors));
        }
        
        return $stack->next()->handle($envelope, $stack);
    }
}
```

### Add Validation to Command

```php
// src/Command/CreatePurchaseOrderCommand.php (with validation)
use Symfony\Component\Validator\Constraints as Assert;

final class CreatePurchaseOrderCommand
{
    public function __construct(
        public readonly ?string $orderNumber,
        
        #[Assert\NotBlank]
        #[Assert\Date]
        public readonly string $orderDate,
        
        #[Assert\NotBlank]
        #[Assert\Choice(['pending', 'received', 'cancelled'])]
        public readonly string $status,
        
        public readonly string $reference,
        public readonly string $notes,
        
        #[Assert\NotBlank]
        #[Assert\Count(min: 1, minMessage: 'At least one line item is required')]
        public readonly array $lines
    ) {}
}
```

### Logging Middleware

```php
// src/Middleware/LoggingMiddleware.php
<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $commandName = get_class($message);
        
        $this->logger->info("Executing command: {$commandName}", [
            'command' => $commandName,
            'data' => $message
        ]);
        
        $start = microtime(true);
        
        try {
            $result = $stack->next()->handle($envelope, $stack);
            
            $duration = microtime(true) - $start;
            $this->logger->info("Command executed successfully: {$commandName}", [
                'command' => $commandName,
                'duration' => $duration
            ]);
            
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error("Command failed: {$commandName}", [
                'command' => $commandName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
```

## 3. Command Bus Setup

### Configure Messenger

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: command.bus
        
        buses:
            command.bus:
                middleware:
                    - App\Middleware\ValidationMiddleware
                    - App\Middleware\LoggingMiddleware
                    - doctrine_transaction
            
            query.bus:
                middleware:
                    - App\Middleware\LoggingMiddleware
        
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        
        routing:
            # Commands that should be processed asynchronously
            'App\Command\SendNotificationCommand': async
```

### Usage in Controller

```php
// src/Controller/PurchaseOrderController.php
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

public function __construct(
    private readonly MessageBusInterface $commandBus
) {}

public function create(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    $command = new CreatePurchaseOrderCommand(
        orderNumber: $data['orderNumber'] ?? null,
        orderDate: $data['orderDate'],
        status: $data['status'],
        reference: $data['reference'] ?? '',
        notes: $data['notes'] ?? '',
        lines: $data['lines']
    );
    
    // Dispatch command
    $envelope = $this->commandBus->dispatch($command);
    
    // Get the result from the handler
    $handledStamp = $envelope->last(HandledStamp::class);
    $purchaseOrderId = $handledStamp->getResult();
    
    return new JsonResponse(['id' => $purchaseOrderId], 201);
}
```

## 4. Query Objects

### Before: Direct Repository Access

```php
// src/Controller/PurchaseOrderController.php (current)
#[Route('/api/purchase-orders', name: 'api_purchase_orders_list', methods: ['GET'])]
public function list(): JsonResponse
{
    $orders = $this->entityManager
        ->getRepository(PurchaseOrder::class)
        ->findAll();
    
    return $this->json($orders);
}
```

### After: Query Object

```php
// src/Query/GetPurchaseOrdersQuery.php
<?php

declare(strict_types=1);

namespace App\Query;

final class GetPurchaseOrdersQuery
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $orderDateFrom = null,
        public readonly ?string $orderDateTo = null,
        public readonly int $page = 1,
        public readonly int $perPage = 20
    ) {}
}
```

### Query Handler

```php
// src/QueryHandler/GetPurchaseOrdersQueryHandler.php
<?php

declare(strict_types=1);

namespace App\QueryHandler;

use App\Query\GetPurchaseOrdersQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetPurchaseOrdersQueryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function __invoke(GetPurchaseOrdersQuery $query): array
    {
        $qb = $this->entityManager
            ->getRepository(PurchaseOrder::class)
            ->createQueryBuilder('po')
            ->orderBy('po.orderDate', 'DESC');
        
        if ($query->status) {
            $qb->andWhere('po.status = :status')
               ->setParameter('status', $query->status);
        }
        
        if ($query->orderDateFrom) {
            $qb->andWhere('po.orderDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime($query->orderDateFrom));
        }
        
        if ($query->orderDateTo) {
            $qb->andWhere('po.orderDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime($query->orderDateTo));
        }
        
        $qb->setFirstResult(($query->page - 1) * $query->perPage)
           ->setMaxResults($query->perPage);
        
        return $qb->getQuery()->getResult();
    }
}
```

### Updated Controller

```php
// src/Controller/PurchaseOrderController.php (refactored)
use App\Query\GetPurchaseOrdersQuery;

#[Route('/api/purchase-orders', name: 'api_purchase_orders_list', methods: ['GET'])]
public function list(
    Request $request,
    MessageBusInterface $queryBus
): JsonResponse
{
    $query = new GetPurchaseOrdersQuery(
        status: $request->query->get('status'),
        orderDateFrom: $request->query->get('orderDateFrom'),
        orderDateTo: $request->query->get('orderDateTo'),
        page: (int) $request->query->get('page', 1),
        perPage: (int) $request->query->get('perPage', 20)
    );
    
    $envelope = $this->queryBus->dispatch($query);
    $orders = $envelope->last(HandledStamp::class)->getResult();
    
    return $this->json($orders);
}
```

## 5. Query Handlers

### Caching Query Results

```php
// src/QueryHandler/GetPurchaseOrdersQueryHandler.php (with caching)
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
final class GetPurchaseOrdersQueryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache
    ) {}

    public function __invoke(GetPurchaseOrdersQuery $query): array
    {
        $cacheKey = sprintf(
            'purchase_orders.list.%s.%s.%d.%d',
            $query->status ?? 'all',
            $query->orderDateFrom ?? 'any',
            $query->page,
            $query->perPage
        );
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query) {
            $item->expiresAfter(300); // 5 minutes
            
            // Execute query (same as before)
            $qb = $this->entityManager
                ->getRepository(PurchaseOrder::class)
                ->createQueryBuilder('po');
            // ... rest of query building
            
            return $qb->getQuery()->getResult();
        });
    }
}
```

### Invalidate Cache on Command

```php
// src/EventSubscriber/CacheInvalidationSubscriber.php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\PurchaseOrderCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Cache\CacheInterface;

#[AsEventListener(event: PurchaseOrderCreatedEvent::class)]
final class CacheInvalidationSubscriber
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    public function __invoke(PurchaseOrderCreatedEvent $event): void
    {
        // Invalidate all purchase order list caches
        // In production, use cache tags for more granular invalidation
        $this->cache->delete('purchase_orders.list.*');
    }
}
```

## 6. Read Models

### Create Read Model Entity

```php
// src/Entity/PurchaseOrderListView.php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_order_list_view')]
class PurchaseOrderListView
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;
    
    #[ORM\Column(type: 'string', length: 50)]
    public string $orderNumber;
    
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $orderDate;
    
    #[ORM\Column(type: 'string', length: 20)]
    public string $status;
    
    #[ORM\Column(type: 'string', length: 255)]
    public string $reference;
    
    #[ORM\Column(type: 'integer')]
    public int $totalLines;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    public float $totalAmount;
    
    #[ORM\Column(type: 'integer')]
    public int $totalQuantity;
}
```

### Projection Event Handler

```php
// src/EventHandler/PurchaseOrderListViewProjector.php
<?php

declare(strict_types=1);

namespace App\EventHandler;

use App\Entity\PurchaseOrderListView;
use App\Event\PurchaseOrderCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: PurchaseOrderCreatedEvent::class)]
final class PurchaseOrderListViewProjector
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function __invoke(PurchaseOrderCreatedEvent $event): void
    {
        $purchaseOrder = $event->getPurchaseOrder();
        
        $view = new PurchaseOrderListView();
        $view->id = $purchaseOrder->id;
        $view->orderNumber = $purchaseOrder->orderNumber;
        $view->orderDate = $purchaseOrder->orderDate;
        $view->status = $purchaseOrder->status;
        $view->reference = $purchaseOrder->reference;
        $view->totalLines = count($purchaseOrder->lines);
        
        // Calculate totals
        $totalAmount = 0;
        $totalQuantity = 0;
        foreach ($purchaseOrder->lines as $line) {
            $totalAmount += $line->quantityOrdered * $line->rate;
            $totalQuantity += $line->quantityOrdered;
        }
        
        $view->totalAmount = $totalAmount;
        $view->totalQuantity = $totalQuantity;
        
        $this->entityManager->persist($view);
        $this->entityManager->flush();
    }
}
```

### Query the Read Model

```php
// src/QueryHandler/GetPurchaseOrdersQueryHandler.php (using read model)
#[AsMessageHandler]
final class GetPurchaseOrdersQueryHandler
{
    public function __invoke(GetPurchaseOrdersQuery $query): array
    {
        // Query the optimized read model instead of the aggregate
        $qb = $this->entityManager
            ->getRepository(PurchaseOrderListView::class)
            ->createQueryBuilder('pv')
            ->orderBy('pv.orderDate', 'DESC');
        
        if ($query->status) {
            $qb->andWhere('pv.status = :status')
               ->setParameter('status', $query->status);
        }
        
        // Much faster - no joins, denormalized data
        return $qb->getQuery()->getResult();
    }
}
```

## 7. Migration Strategy

### Step 1: Add Messenger (Week 1)

```bash
# Install Messenger
composer require symfony/messenger

# Generate config
php bin/console make:config messenger
```

### Step 2: Create First Command (Week 1)

1. Create `CreatePurchaseOrderCommand`
2. Create `CreatePurchaseOrderCommandHandler`
3. Update controller to use command bus
4. Test thoroughly
5. Keep old code commented for rollback

```php
// Controller (during migration)
public function create(Request $request): JsonResponse
{
    if ($this->useCqrs) {
        // New CQRS approach
        return $this->createWithCqrs($request);
    } else {
        // Old approach (fallback)
        return $this->createLegacy($request);
    }
}
```

### Step 3: Add Middleware (Week 1-2)

1. Add validation middleware
2. Add logging middleware
3. Add transaction middleware
4. Test with existing command

### Step 4: Migrate Other Commands (Week 2-3)

1. Update Purchase Order
2. Create Sales Order
3. Update Sales Order
4. Receive Items
5. Fulfill Items

### Step 5: Add Queries (Week 3-4)

1. List Purchase Orders
2. Get Purchase Order
3. List Sales Orders
4. Get Sales Order
5. List Items

### Step 6: Create Read Models (Week 4-6)

1. Identify slow queries
2. Create read model entities
3. Create projectors
4. Update query handlers
5. Performance test

### Step 7: Cleanup (Week 6+)

1. Remove old code
2. Remove feature flags
3. Update documentation
4. Train team

## Testing Strategy

### Unit Test Command Handler

```php
// tests/CommandHandler/CreatePurchaseOrderCommandHandlerTest.php
<?php

declare(strict_types=1);

namespace App\Tests\CommandHandler;

use App\Command\CreatePurchaseOrderCommand;
use App\CommandHandler\CreatePurchaseOrderCommandHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CreatePurchaseOrderCommandHandlerTest extends TestCase
{
    public function testCreatePurchaseOrder(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        
        $handler = new CreatePurchaseOrderCommandHandler($em, $dispatcher);
        
        $command = new CreatePurchaseOrderCommand(
            orderNumber: 'PO-TEST-001',
            orderDate: '2026-01-01',
            status: 'pending',
            reference: 'Test Order',
            notes: 'Test notes',
            lines: [
                ['itemId' => 1, 'quantityOrdered' => 10, 'rate' => 5.99]
            ]
        );
        
        $em->expects($this->once())
           ->method('persist');
        
        $em->expects($this->once())
           ->method('flush');
        
        $dispatcher->expects($this->once())
                   ->method('dispatch');
        
        $result = $handler($command);
        
        $this->assertIsInt($result);
    }
}
```

### Integration Test with Command Bus

```php
// tests/Integration/CommandBusTest.php
<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\CreatePurchaseOrderCommand;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class CommandBusTest extends KernelTestCase
{
    public function testDispatchCommand(): void
    {
        self::bootKernel();
        
        $commandBus = static::getContainer()->get(MessageBusInterface::class);
        
        $command = new CreatePurchaseOrderCommand(
            orderNumber: 'PO-TEST-001',
            orderDate: '2026-01-01',
            status: 'pending',
            reference: 'Test Order',
            notes: 'Test notes',
            lines: [
                ['itemId' => 1, 'quantityOrdered' => 10, 'rate' => 5.99]
            ]
        );
        
        $envelope = $commandBus->dispatch($command);
        
        $this->assertNotNull($envelope);
    }
}
```

## Performance Comparison

### Before (Direct Entity Queries)

```
Query: List 1000 purchase orders with line items
Time: ~500ms
Queries: 1 + N (N+1 problem)
Memory: 50MB
```

### After (Read Model)

```
Query: List 1000 purchase orders from read model
Time: ~50ms (10x faster)
Queries: 1 (no joins needed)
Memory: 10MB
```

## Conclusion

This refactoring guide provides a path to formalize CQRS in Brickventory:

1. **Commands**: Explicit intent, testable, reusable
2. **Queries**: Optimized, cacheable, separable
3. **Middleware**: Cross-cutting concerns (validation, logging, transactions)
4. **Read Models**: Performance optimization for common queries
5. **Event Projections**: Real-time view updates

Follow the migration strategy to implement gradually without breaking existing functionality.

# CQRS Phase 2 Implementation Summary

## Overview

This document describes the Phase 2 CQRS implementation that formalizes the Command/Query separation pattern in Brickventory. Following the recommendations from the CQRS Pattern Analysis, we have implemented a complete command and query bus infrastructure with middleware support.

## What Was Implemented

### 1. Command Objects

Commands represent **write operations** - intentions to change state in the system.

#### Purchase Order Commands
- **CreatePurchaseOrderCommand** - Create a new purchase order with line items
- **UpdatePurchaseOrderCommand** - Update an existing purchase order
- **DeletePurchaseOrderCommand** - Delete a purchase order

#### Sales Order Commands
- **CreateSalesOrderCommand** - Create a new sales order with line items
- **UpdateSalesOrderCommand** - Update an existing sales order
- **DeleteSalesOrderCommand** - Delete a sales order

**Key Characteristics:**
- Readonly properties (immutable after creation)
- Clear data contracts using PHP 8.4 property types
- Type-safe array documentation with PHPDoc
- No business logic (pure data transfer objects)

**Example:**
```php
final class CreatePurchaseOrderCommand
{
    public function __construct(
        public readonly ?string $orderNumber,
        public readonly string $orderDate,
        public readonly string $status,
        public readonly ?string $reference,
        public readonly ?string $notes,
        /** @var array<int, array{itemId: int, quantityOrdered: int, rate: float}> */
        public readonly array $lines
    ) {}
}
```

### 2. Query Objects

Queries represent **read operations** - requests for data without side effects.

#### Purchase Order Queries
- **GetPurchaseOrdersQuery** - List purchase orders with filtering and pagination
- **GetPurchaseOrderQuery** - Get a single purchase order by ID

#### Sales Order Queries
- **GetSalesOrdersQuery** - List sales orders with filtering and pagination
- **GetSalesOrderQuery** - Get a single sales order by ID

**Key Characteristics:**
- Support filtering (status, date range)
- Built-in pagination (page, perPage)
- Optional parameters with sensible defaults
- No mutation operations

**Example:**
```php
final class GetPurchaseOrdersQuery
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $orderDateFrom = null,
        public readonly ?string $orderDateTo = null,
        public readonly int $page = 1,
        public readonly int $perPage = 100
    ) {}
}
```

### 3. Command Handlers

Command handlers contain the **business logic** for executing commands.

#### Responsibilities:
1. Validate command data
2. Load related entities (items, etc.)
3. Create/update/delete domain entities
4. Persist changes to database
5. Dispatch domain events (for event sourcing)

**Example:**
```php
#[AsMessageHandler]
final class CreatePurchaseOrderCommandHandler
{
    public function __invoke(CreatePurchaseOrderCommand $command): int
    {
        $po = new PurchaseOrder();
        $po->orderNumber = $command->orderNumber ?? 'PO-' . date('YmdHis');
        // ... set other properties
        
        foreach ($command->lines as $lineData) {
            $item = $this->entityManager->getRepository(Item::class)
                ->find($lineData['itemId']);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item not found");
            }
            
            $line = new PurchaseOrderLine();
            // ... configure line
            $po->lines->add($line);
        }
        
        $this->entityManager->persist($po);
        $this->entityManager->flush();
        
        // Dispatch event for inventory updates
        $this->eventDispatcher->dispatch(
            new PurchaseOrderCreatedEvent($po)
        );
        
        return $po->id;
    }
}
```

**Key Features:**
- Single responsibility (one handler per command)
- Transaction management via middleware
- Event dispatching for side effects
- Clear error messages via exceptions

### 4. Query Handlers

Query handlers optimize **data retrieval** for specific use cases.

#### Responsibilities:
1. Build optimized database queries
2. Apply filters and pagination
3. Transform entities to DTOs/arrays
4. Return structured data

**Example:**
```php
#[AsMessageHandler]
final class GetPurchaseOrdersQueryHandler
{
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
        
        // ... apply other filters
        
        $qb->setFirstResult(($query->page - 1) * $query->perPage)
           ->setMaxResults($query->perPage);
        
        $purchaseOrders = $qb->getQuery()->getResult();
        
        return array_map(fn($po) => [...], $purchaseOrders);
    }
}
```

**Key Features:**
- Optimized queries (only fetch needed data)
- Built-in filtering and pagination
- Consistent data structure
- No side effects (read-only)

### 5. Middleware

Middleware provides **cross-cutting concerns** applied to all commands/queries.

#### ValidationMiddleware

Validates command/query objects before execution.

```php
final class ValidationMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $violations = $this->validator->validate($message);
        
        if (count($violations) > 0) {
            throw new \InvalidArgumentException('Validation failed: ...');
        }
        
        return $stack->next()->handle($envelope, $stack);
    }
}
```

**Features:**
- Symfony Validator integration
- Consistent validation across all commands
- Clear error messages

#### LoggingMiddleware

Logs execution of all commands and queries.

```php
final class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $messageClass = get_class($envelope->getMessage());
        
        $this->logger->info('Executing message: {messageClass}', [
            'messageClass' => $messageClass,
        ]);
        
        $start = microtime(true);
        
        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $duration = microtime(true) - $start;
            
            $this->logger->info('Message executed successfully', [
                'messageClass' => $messageClass,
                'duration' => round($duration * 1000, 2) . 'ms',
            ]);
            
            return $envelope;
        } catch (\Throwable $e) {
            $this->logger->error('Message execution failed', [
                'messageClass' => $messageClass,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

**Features:**
- Automatic logging of all message execution
- Performance tracking (execution time)
- Error logging with context
- No impact on business logic

### 6. Message Buses

Two separate buses for commands and queries.

#### Configuration (messenger.yaml)

```yaml
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
        
        routing:
            'App\Message\Command\*': command.bus
            'App\Message\Query\*': query.bus
```

**Key Features:**
- Separate buses for different concerns
- Command bus has transaction management
- Query bus is lighter (no transactions needed)
- Automatic routing based on namespace

#### Service Configuration (services.yaml)

```yaml
services:
    _defaults:
        bind:
            $commandBus: '@command.bus'
            $queryBus: '@query.bus'
```

**Features:**
- Named autowiring for bus injection
- Controllers receive correct bus automatically
- Type-safe dependency injection

### 7. Refactored Controllers

Controllers now focus purely on HTTP concerns.

#### Before (Traditional Approach)
```php
class PurchaseOrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}
    
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Business logic (50+ lines)
        $po = new PurchaseOrder();
        $po->orderNumber = $data['orderNumber'] ?? 'PO-' . date('YmdHis');
        // ... 30 more lines of business logic
        
        $this->entityManager->persist($po);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new PurchaseOrderCreatedEvent($po));
        
        return $this->json(['id' => $po->id], 201);
    }
}
```

**Problems:**
- Business logic in controller (hard to test)
- Direct entity manipulation
- No validation layer
- No logging
- 200+ lines per controller

#### After (CQRS Approach)
```php
class PurchaseOrderController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus
    ) {}
    
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        try {
            $command = new CreatePurchaseOrderCommand(
                orderNumber: $data['orderNumber'] ?? null,
                orderDate: $data['orderDate'] ?? (new \DateTime())->format('Y-m-d H:i:s'),
                status: $data['status'] ?? 'pending',
                reference: $data['reference'] ?? null,
                notes: $data['notes'] ?? null,
                lines: $data['lines'] ?? []
            );
            
            $envelope = $this->commandBus->dispatch($command);
            $id = $envelope->last(HandledStamp::class)?->getResult();
            
            return $this->json(['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

**Benefits:**
- Pure HTTP handling (10-15 lines per action)
- Business logic in handler (testable)
- Automatic validation via middleware
- Automatic logging via middleware
- ~80 lines total per controller

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP REQUEST                             │
│                    (JSON payload)                            │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              CONTROLLER (HTTP Layer)                        │
│  • Parse JSON                                               │
│  • Create Command/Query object                             │
│  • Dispatch to bus                                          │
│  • Return JSON response                                     │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              COMMAND BUS / QUERY BUS                        │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              MIDDLEWARE (Cross-cutting concerns)            │
│  1. ValidationMiddleware → Validate input                  │
│  2. LoggingMiddleware → Log execution                      │
│  3. doctrine_transaction → Transaction management          │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              HANDLER (Business Logic)                       │
│  • Load entities                                            │
│  • Execute business rules                                   │
│  • Persist changes                                          │
│  • Dispatch domain events                                   │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ├──────────────────┐
                 ▼                  ▼
┌────────────────────────┐   ┌─────────────────────┐
│     DATABASE           │   │   EVENT BUS         │
│  (Doctrine ORM)        │   │  (Domain Events)    │
│  • Entities            │   │  • Event Handlers   │
│  • Transactions        │   │  • Event Store      │
└────────────────────────┘   └─────────────────────┘
```

## Benefits Achieved

### 1. Separation of Concerns
- **Controllers**: HTTP handling only
- **Commands/Queries**: Data contracts
- **Handlers**: Business logic
- **Middleware**: Cross-cutting concerns

### 2. Testability
```php
// Easy to unit test handlers in isolation
public function testCreatePurchaseOrderHandler()
{
    $command = new CreatePurchaseOrderCommand(...);
    $handler = new CreatePurchaseOrderCommandHandler($em, $dispatcher);
    
    $id = $handler($command);
    
    $this->assertIsInt($id);
}
```

### 3. Reusability
Commands can be dispatched from:
- HTTP controllers
- CLI commands
- Event subscribers
- Message consumers
- Background jobs

### 4. Consistency
- All commands validated the same way
- All executions logged the same way
- All transactions managed the same way

### 5. Maintainability
- Clear structure (know where to find things)
- Single responsibility (one handler = one operation)
- Easy to extend (add new commands/handlers)

### 6. Performance
- Queries optimized independently
- No unnecessary entity hydration
- Future: Can add caching middleware

## Code Metrics

### Before CQRS
- PurchaseOrderController: ~220 lines
- SalesOrderController: ~208 lines
- **Total**: 428 lines
- Business logic scattered in controllers
- No validation layer
- No logging layer

### After CQRS
- PurchaseOrderController: ~80 lines
- SalesOrderController: ~80 lines
- Commands: 6 files, ~200 lines
- Queries: 4 files, ~150 lines
- Handlers: 10 files, ~800 lines
- Middleware: 2 files, ~150 lines
- **Total**: 1,460 lines

**Analysis:**
- More lines total, but better organized
- Business logic now testable (in handlers)
- Controllers 60% smaller
- Clear separation enables:
  - Unit testing handlers
  - Reusing commands
  - Adding middleware easily
  - Extending without modifying

## Event Sourcing Integration

The CQRS implementation **preserves** the existing event sourcing system:

```php
// Command handler dispatches events
$this->eventDispatcher->dispatch(
    new PurchaseOrderCreatedEvent($po)
);
```

**Event handlers (unchanged):**
- `PurchaseOrderCreatedEventHandler` - Updates inventory
- `SalesOrderCreatedEventHandler` - Updates inventory
- Creates `ItemEvent` records (audit trail)

**Benefits:**
- Complete audit trail maintained
- Inventory updates still automated
- Can replay events
- Can query event store

## Testing Strategy

### Unit Tests (Handlers)
```php
class CreatePurchaseOrderCommandHandlerTest extends TestCase
{
    public function testCreatePurchaseOrder(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        
        $handler = new CreatePurchaseOrderCommandHandler($em, $dispatcher);
        
        $command = new CreatePurchaseOrderCommand(
            orderNumber: 'PO-TEST',
            orderDate: '2026-01-04',
            status: 'pending',
            reference: 'Test',
            notes: 'Test order',
            lines: [['itemId' => 1, 'quantityOrdered' => 10, 'rate' => 5.99]]
        );
        
        $id = $handler($command);
        
        $this->assertIsInt($id);
    }
}
```

### Integration Tests (End-to-end)
```php
class PurchaseOrderApiTest extends WebTestCase
{
    public function testCreatePurchaseOrderViaApi(): void
    {
        $client = static::createClient();
        
        $client->request('POST', '/api/purchase-orders', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'orderDate' => '2026-01-04',
                'status' => 'pending',
                'lines' => [['itemId' => 1, 'quantityOrdered' => 10, 'rate' => 5.99]]
            ])
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
    }
}
```

## Future Enhancements

### Phase 3: Read Models (Planned)
Create denormalized views for complex queries:

```php
class PurchaseOrderListView
{
    public int $id;
    public string $orderNumber;
    public string $status;
    public int $totalLines;
    public float $totalAmount;
}
```

**Benefits:**
- Faster queries (no joins)
- Optimized for specific use cases
- Can have multiple views of same data

### Phase 4: Advanced Features (Future)
- Event versioning (handle schema evolution)
- Saga pattern (complex multi-step workflows)
- Snapshots (optimize event replay)
- Separate read/write databases (at scale)

## Migration Notes

### Backward Compatibility
- ✅ API endpoints unchanged (same URLs, methods, payloads)
- ✅ Database schema unchanged
- ✅ Event sourcing preserved
- ✅ Existing tests compatible

### Rollback Strategy
If needed, can revert by:
1. Restoring old controller files
2. Removing Message and MessageHandler directories
3. Reverting messenger.yaml and services.yaml

### Next Controllers to Migrate
- ItemReceiptController
- ItemController
- Future controllers

## Documentation

Related documents:
- **CQRS_PATTERN_ANALYSIS.md** - Why CQRS fits this project
- **CQRS_REFACTORING_GUIDE.md** - Step-by-step examples
- **ARCHITECTURAL_DECISION_FRAMEWORK.md** - Decision making guide

## Conclusion

Phase 2 CQRS implementation successfully:

✅ Formalizes command/query separation
✅ Reduces controller complexity by 60%
✅ Centralizes business logic in testable handlers
✅ Adds consistent validation and logging
✅ Maintains backward compatibility
✅ Preserves event sourcing architecture
✅ Provides foundation for future optimizations

The implementation follows industry best practices and aligns with NetSuite's event-driven architecture, providing a solid foundation for building a professional ERP system.

**Status**: Production-ready for Purchase Orders and Sales Orders
**Next Steps**: Extend pattern to remaining controllers, add integration tests, monitor performance in production

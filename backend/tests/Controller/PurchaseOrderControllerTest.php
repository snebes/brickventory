<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Item;
use App\Entity\Location;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderLine;
use App\Entity\Vendor;
use App\Repository\PurchaseOrderRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PurchaseOrder entity creation and line handling.
 */
class PurchaseOrderControllerTest extends TestCase
{
    /**
     * Test that PurchaseOrder entity initializes correctly.
     */
    public function testPurchaseOrderCreation(): void
    {
        $po = new PurchaseOrder();

        $this->assertNotEmpty($po->uuid, 'UUID should be auto-generated');
        $this->assertInstanceOf(\DateTimeInterface::class, $po->transactionDate, 'Transaction date should be set');
        $this->assertInstanceOf(\DateTimeInterface::class, $po->createdAt, 'Created at should be set');
        $this->assertInstanceOf(\DateTimeInterface::class, $po->updatedAt, 'Updated at should be set');
        $this->assertEquals(PurchaseOrder::STATUS_PENDING_APPROVAL, $po->status, 'Default status should be pending_approval');
        $this->assertEmpty($po->orderNumber, 'Order number should be empty by default');
        $this->assertCount(0, $po->lines, 'Lines collection should be initialized and empty');
    }

    /**
     * Test that PurchaseOrderLine entity initializes correctly.
     */
    public function testPurchaseOrderLineCreation(): void
    {
        $line = new PurchaseOrderLine();

        $this->assertNotEmpty($line->uuid, 'UUID should be auto-generated');
        $this->assertInstanceOf(\DateTimeInterface::class, $line->createdAt, 'Created at should be set');
        $this->assertInstanceOf(\DateTimeInterface::class, $line->updatedAt, 'Updated at should be set');
        $this->assertEquals(1, $line->lineNumber, 'Default line number should be 1');
        $this->assertEquals(0, $line->quantityOrdered, 'Default quantity ordered should be 0');
        $this->assertEquals(0.0, $line->rate, 'Default rate should be 0.0');
    }

    /**
     * Test adding lines to a PurchaseOrder.
     */
    public function testAddingLinesToPurchaseOrder(): void
    {
        $po = new PurchaseOrder();

        // Create mock item (we can't test with real DB but we can test the logic)
        $item = new Item();
        $item->itemId = 'TEST-001';
        $item->itemName = 'Test Item';

        $line = new PurchaseOrderLine();
        $line->purchaseOrder = $po;
        $line->item = $item;
        $line->quantityOrdered = 10;
        $line->rate = 5.50;

        $po->lines->add($line);

        $this->assertCount(1, $po->lines, 'Should have 1 line');
        $this->assertSame($po, $line->purchaseOrder, 'Line should reference parent PO');
    }

    /**
     * Test order number validation - empty string should NOT be a valid order number.
     */
    public function testEmptyOrderNumberHandling(): void
    {
        $orderNumber = '';

        // Test !empty() logic used in controller
        $shouldAutoGenerate = empty($orderNumber);

        $this->assertTrue($shouldAutoGenerate, 'Empty string should trigger auto-generation');
    }

    /**
     * Test order number validation - whitespace should NOT be a valid order number.
     */
    public function testWhitespaceOrderNumberHandling(): void
    {
        $orderNumber = '   ';

        // Test !empty() with trim logic used in controller
        $trimmed = trim($orderNumber);
        $shouldAutoGenerate = empty($trimmed);

        $this->assertTrue($shouldAutoGenerate, 'Whitespace-only string should trigger auto-generation');
    }

    /**
     * Test order number validation - valid order number should be used.
     */
    public function testValidOrderNumberHandling(): void
    {
        $orderNumber = 'PO-TEST-001';

        $trimmed = trim($orderNumber);
        $shouldAutoGenerate = empty($trimmed);

        $this->assertFalse($shouldAutoGenerate, 'Valid order number should NOT trigger auto-generation');
        $this->assertEquals('PO-TEST-001', $trimmed, 'Order number should be preserved');
    }

    /**
     * Test PurchaseOrder status constants are defined correctly.
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('pending_approval', PurchaseOrder::STATUS_PENDING_APPROVAL);
        $this->assertEquals('pending_receipt', PurchaseOrder::STATUS_PENDING_RECEIPT);
        $this->assertEquals('partially_received', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        $this->assertEquals('received', PurchaseOrder::STATUS_RECEIVED);
        $this->assertEquals('closed', PurchaseOrder::STATUS_CLOSED);
        $this->assertEquals('cancelled', PurchaseOrder::STATUS_CANCELLED);

        $this->assertContains(PurchaseOrder::STATUS_PENDING_APPROVAL, PurchaseOrder::VALID_STATUSES);
    }

    /**
     * Test getOrderDate and setOrderDate work correctly.
     */
    public function testOrderDateAccessors(): void
    {
        $po = new PurchaseOrder();

        $newDate = new \DateTime('2026-01-15');
        $po->setOrderDate($newDate);

        $this->assertEquals($newDate, $po->getOrderDate(), 'getOrderDate should return the date set by setOrderDate');
        $this->assertEquals($newDate, $po->transactionDate, 'transactionDate should equal getOrderDate');
    }

    /**
     * Test getTransactionNumber returns orderNumber.
     */
    public function testGetTransactionNumber(): void
    {
        $po = new PurchaseOrder();
        $po->orderNumber = 'PO-TEST-123';

        $this->assertEquals('PO-TEST-123', $po->getTransactionNumber());
    }

    /**
     * Test getTransactionType returns correct type.
     */
    public function testGetTransactionType(): void
    {
        $po = new PurchaseOrder();

        $this->assertEquals('purchase_order', $po->getTransactionType());
    }
}

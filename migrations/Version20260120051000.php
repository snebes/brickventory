<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Expand PurchaseOrder and PurchaseOrderLine entities for NetSuite ERP workflow
 */
final class Version20260120051000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand PurchaseOrder and PurchaseOrderLine for NetSuite ERP workflow with vendor, approval, and financial tracking';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to purchase_order table
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN vendor_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN expected_receipt_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN ship_to_location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN bill_to_address JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN shipping_method VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN payment_terms VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN exchange_rate DECIMAL(10, 6) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN tax_total DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN shipping_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN total DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN buyer_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN department_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN approved_by INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN approved_at TIMESTAMP DEFAULT NULL');

        // Update status values: 'pending' -> 'Pending Approval'
        $this->addSql("UPDATE purchase_order SET status = 'Pending Approval' WHERE status = 'pending'");

        // Add foreign key constraint for vendor
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT fk_po_vendor FOREIGN KEY (vendor_id) REFERENCES vendor(id) ON DELETE RESTRICT');

        // Create indexes for purchase_order
        $this->addSql('CREATE INDEX idx_po_vendor ON purchase_order (vendor_id)');
        $this->addSql('CREATE INDEX idx_po_status ON purchase_order (status)');
        $this->addSql('CREATE INDEX idx_po_expected_date ON purchase_order (expected_receipt_date)');

        // Add new columns to purchase_order_line table
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN quantity_billed INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN tax_rate DECIMAL(5, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN tax_amount DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN expense_account_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN expected_receipt_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN closed BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN closed_reason VARCHAR(50) DEFAULT NULL');

        // Create indexes for purchase_order_line
        $this->addSql('CREATE INDEX idx_po_line_closed ON purchase_order_line (closed)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes from purchase_order_line
        $this->addSql('DROP INDEX IF EXISTS idx_po_line_closed');

        // Remove columns from purchase_order_line
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN quantity_billed');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN tax_rate');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN tax_amount');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN expense_account_id');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN expected_receipt_date');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN closed');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN closed_reason');

        // Drop indexes from purchase_order
        $this->addSql('DROP INDEX IF EXISTS idx_po_vendor');
        $this->addSql('DROP INDEX IF EXISTS idx_po_status');
        $this->addSql('DROP INDEX IF EXISTS idx_po_expected_date');

        // Drop foreign key
        $this->addSql('ALTER TABLE purchase_order DROP CONSTRAINT IF EXISTS fk_po_vendor');

        // Remove columns from purchase_order
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN vendor_id');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN expected_receipt_date');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN ship_to_location_id');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN bill_to_address');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN shipping_method');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN payment_terms');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN currency');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN exchange_rate');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN subtotal');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN tax_total');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN shipping_cost');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN total');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN buyer_id');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN department_id');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN approved_by');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN approved_at');

        // Revert status values
        $this->addSql("UPDATE purchase_order SET status = 'pending' WHERE status = 'Pending Approval'");
    }
}

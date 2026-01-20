<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create VendorBill and VendorBillLine tables for NetSuite ERP workflow
 */
final class Version20260120055000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create VendorBill and VendorBillLine tables for accounts payable management';
    }

    public function up(Schema $schema): void
    {
        // Create vendor_bill table
        $this->addSql('CREATE TABLE vendor_bill (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            bill_number VARCHAR(55) UNIQUE NOT NULL,
            vendor_id INTEGER NOT NULL,
            vendor_invoice_number VARCHAR(100) DEFAULT NULL,
            vendor_invoice_date DATE DEFAULT NULL,
            bill_date DATE NOT NULL,
            due_date DATE NOT NULL,
            payment_terms VARCHAR(50) DEFAULT NULL,
            purchase_order_id INTEGER DEFAULT NULL,
            item_receipt_id INTEGER DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'Open\',
            currency VARCHAR(3) DEFAULT NULL,
            exchange_rate DECIMAL(10, 6) DEFAULT NULL,
            subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            tax_total DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            freight_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            total DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            amount_due DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            approved_by INTEGER DEFAULT NULL,
            approved_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_bill_vendor FOREIGN KEY (vendor_id) REFERENCES vendor(id) ON DELETE RESTRICT,
            CONSTRAINT fk_bill_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_order(id) ON DELETE SET NULL,
            CONSTRAINT fk_bill_receipt FOREIGN KEY (item_receipt_id) REFERENCES item_receipt(id) ON DELETE SET NULL
        )');

        // Create indexes for vendor_bill
        $this->addSql('CREATE INDEX idx_bill_vendor ON vendor_bill (vendor_id)');
        $this->addSql('CREATE INDEX idx_bill_status ON vendor_bill (status)');
        $this->addSql('CREATE INDEX idx_bill_due_date ON vendor_bill (due_date)');
        $this->addSql('CREATE INDEX idx_bill_vendor_invoice ON vendor_bill (vendor_invoice_number)');
        $this->addSql('CREATE INDEX idx_bill_po ON vendor_bill (purchase_order_id)');
        $this->addSql('CREATE INDEX idx_bill_receipt ON vendor_bill (item_receipt_id)');

        // Create vendor_bill_line table
        $this->addSql('CREATE TABLE vendor_bill_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            vendor_bill_id INTEGER NOT NULL,
            line_type VARCHAR(50) NOT NULL DEFAULT \'Item\',
            item_id INTEGER DEFAULT NULL,
            receipt_line_id INTEGER DEFAULT NULL,
            po_line_id INTEGER DEFAULT NULL,
            description VARCHAR(255) NOT NULL DEFAULT \'\',
            quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            amount DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            expense_account_id INTEGER DEFAULT NULL,
            variance_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.0,
            variance_reason TEXT DEFAULT NULL,
            CONSTRAINT fk_bill_line_bill FOREIGN KEY (vendor_bill_id) REFERENCES vendor_bill(id) ON DELETE CASCADE,
            CONSTRAINT fk_bill_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
            CONSTRAINT fk_bill_line_receipt_line FOREIGN KEY (receipt_line_id) REFERENCES item_receipt_line(id) ON DELETE SET NULL,
            CONSTRAINT fk_bill_line_po_line FOREIGN KEY (po_line_id) REFERENCES purchase_order_line(id) ON DELETE SET NULL
        )');

        // Create indexes for vendor_bill_line
        $this->addSql('CREATE INDEX idx_bill_line_bill ON vendor_bill_line (vendor_bill_id)');
        $this->addSql('CREATE INDEX idx_bill_line_item ON vendor_bill_line (item_id)');
        $this->addSql('CREATE INDEX idx_bill_line_receipt ON vendor_bill_line (receipt_line_id)');
        $this->addSql('CREATE INDEX idx_bill_line_po ON vendor_bill_line (po_line_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_bill_line_bill');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_line_item');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_line_receipt');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_line_po');

        // Drop vendor_bill_line table
        $this->addSql('DROP TABLE vendor_bill_line');

        // Drop indexes
        $this->addSql('DROP INDEX IF EXISTS idx_bill_vendor');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_status');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_due_date');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_vendor_invoice');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_po');
        $this->addSql('DROP INDEX IF EXISTS idx_bill_receipt');

        // Drop vendor_bill table
        $this->addSql('DROP TABLE vendor_bill');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Expand ItemReceipt and ItemReceiptLine entities for NetSuite ERP workflow
 */
final class Version20260120052000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expand ItemReceipt and ItemReceiptLine for NetSuite ERP workflow with inspection, landed costs, and lot/serial tracking';
    }

    public function up(Schema $schema): void
    {
        // Add new columns to item_receipt table
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN vendor_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN received_at_location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN vendor_shipment_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN carrier VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN tracking_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN freight_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.0');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN landed_cost_category VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN inspector_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN inspection_notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN bill_immediately BOOLEAN NOT NULL DEFAULT FALSE');

        // Update status values: 'received' -> 'Received'
        $this->addSql("UPDATE item_receipt SET status = 'Received' WHERE status = 'received'");

        // Add foreign key constraint for vendor
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT fk_receipt_vendor FOREIGN KEY (vendor_id) REFERENCES vendor(id) ON DELETE RESTRICT');

        // Create indexes for item_receipt
        $this->addSql('CREATE INDEX idx_receipt_vendor ON item_receipt (vendor_id)');
        $this->addSql('CREATE INDEX idx_receipt_status ON item_receipt (status)');
        $this->addSql('CREATE INDEX idx_receipt_tracking ON item_receipt (tracking_number)');

        // Add new columns to item_receipt_line table
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN quantity_accepted INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN quantity_rejected INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN bin_location VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN lot_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN serial_numbers JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN expiration_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN receiving_notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN cost_layer_id INTEGER DEFAULT NULL');

        // Set default quantityAccepted = quantityReceived for existing records
        $this->addSql('UPDATE item_receipt_line SET quantity_accepted = quantity_received WHERE quantity_accepted = 0');

        // Add foreign key constraint for cost_layer
        $this->addSql('ALTER TABLE item_receipt_line ADD CONSTRAINT fk_receipt_line_cost_layer FOREIGN KEY (cost_layer_id) REFERENCES cost_layer(id) ON DELETE SET NULL');

        // Create indexes for item_receipt_line
        $this->addSql('CREATE INDEX idx_receipt_line_lot ON item_receipt_line (lot_number)');
        $this->addSql('CREATE INDEX idx_receipt_line_cost_layer ON item_receipt_line (cost_layer_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes from item_receipt_line
        $this->addSql('DROP INDEX IF EXISTS idx_receipt_line_lot');
        $this->addSql('DROP INDEX IF EXISTS idx_receipt_line_cost_layer');

        // Drop foreign key
        $this->addSql('ALTER TABLE item_receipt_line DROP CONSTRAINT IF EXISTS fk_receipt_line_cost_layer');

        // Remove columns from item_receipt_line
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN quantity_accepted');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN quantity_rejected');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN bin_location');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN lot_number');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN serial_numbers');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN expiration_date');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN receiving_notes');
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN cost_layer_id');

        // Drop indexes from item_receipt
        $this->addSql('DROP INDEX IF EXISTS idx_receipt_vendor');
        $this->addSql('DROP INDEX IF EXISTS idx_receipt_status');
        $this->addSql('DROP INDEX IF EXISTS idx_receipt_tracking');

        // Drop foreign key
        $this->addSql('ALTER TABLE item_receipt DROP CONSTRAINT IF EXISTS fk_receipt_vendor');

        // Remove columns from item_receipt
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN vendor_id');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN received_at_location_id');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN vendor_shipment_number');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN carrier');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN tracking_number');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN freight_cost');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN landed_cost_category');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN inspector_id');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN inspection_notes');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN bill_immediately');

        // Revert status values
        $this->addSql("UPDATE item_receipt SET status = 'received' WHERE status = 'Received'");
    }
}

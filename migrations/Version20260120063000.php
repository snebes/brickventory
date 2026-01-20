<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add proper Location foreign keys to existing tables for Phase 2
 */
final class Version20260120063000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Location FKs to PurchaseOrder, PurchaseOrderLine, ItemReceipt, SalesOrder, SalesOrderLine, ItemFulfillment';
    }

    public function up(Schema $schema): void
    {
        // Update purchase_order: change shipToLocationId from integer to foreign key
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN ship_to_location_id_new INTEGER DEFAULT NULL');
        $this->addSql('UPDATE purchase_order SET ship_to_location_id_new = ship_to_location_id WHERE ship_to_location_id IS NOT NULL');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN ship_to_location_id');
        $this->addSql('ALTER TABLE purchase_order RENAME COLUMN ship_to_location_id_new TO ship_to_location_id');
        $this->addSql('ALTER TABLE purchase_order ADD CONSTRAINT fk_po_ship_to_location FOREIGN KEY (ship_to_location_id) REFERENCES location(id) ON DELETE SET NULL');

        // Add receiving location fields to purchase_order_line
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN receiving_location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order_line ADD COLUMN receiving_bin_location VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_order_line ADD CONSTRAINT fk_po_line_receiving_location FOREIGN KEY (receiving_location_id) REFERENCES location(id) ON DELETE SET NULL');

        // Update item_receipt: change receivedAtLocationId from integer to foreign key
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN received_at_location_id_new INTEGER DEFAULT NULL');
        $this->addSql('UPDATE item_receipt SET received_at_location_id_new = received_at_location_id WHERE received_at_location_id IS NOT NULL');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN received_at_location_id');
        $this->addSql('ALTER TABLE item_receipt RENAME COLUMN received_at_location_id_new TO received_at_location_id');
        $this->addSql('ALTER TABLE item_receipt ADD CONSTRAINT fk_receipt_location FOREIGN KEY (received_at_location_id) REFERENCES location(id) ON DELETE SET NULL');

        // Add fulfillment location to sales_order
        $this->addSql('ALTER TABLE sales_order ADD COLUMN fulfill_from_location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_order ADD CONSTRAINT fk_so_fulfill_from_location FOREIGN KEY (fulfill_from_location_id) REFERENCES location(id) ON DELETE SET NULL');

        // Add fulfillment location and bin to sales_order_line
        $this->addSql('ALTER TABLE sales_order_line ADD COLUMN fulfill_from_location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_order_line ADD COLUMN pick_from_bin_location VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales_order_line ADD CONSTRAINT fk_so_line_fulfill_location FOREIGN KEY (fulfill_from_location_id) REFERENCES location(id) ON DELETE SET NULL');

        // Add fulfillment location to item_fulfillment
        $this->addSql('ALTER TABLE item_fulfillment ADD COLUMN fulfill_from_location_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE item_fulfillment ADD CONSTRAINT fk_fulfillment_location FOREIGN KEY (fulfill_from_location_id) REFERENCES location(id) ON DELETE SET NULL');

        // Set default location for existing records
        $this->addSql('UPDATE purchase_order SET ship_to_location_id = (SELECT id FROM location WHERE location_code = \'DEFAULT\') WHERE ship_to_location_id IS NULL');
        $this->addSql('UPDATE item_receipt SET received_at_location_id = (SELECT id FROM location WHERE location_code = \'DEFAULT\') WHERE received_at_location_id IS NULL');
        $this->addSql('UPDATE sales_order SET fulfill_from_location_id = (SELECT id FROM location WHERE location_code = \'DEFAULT\') WHERE fulfill_from_location_id IS NULL');
        $this->addSql('UPDATE item_fulfillment SET fulfill_from_location_id = (SELECT id FROM location WHERE location_code = \'DEFAULT\') WHERE fulfill_from_location_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign keys and revert to integer columns
        $this->addSql('ALTER TABLE item_fulfillment DROP CONSTRAINT IF EXISTS fk_fulfillment_location');
        $this->addSql('ALTER TABLE item_fulfillment DROP COLUMN IF EXISTS fulfill_from_location_id');

        $this->addSql('ALTER TABLE sales_order_line DROP CONSTRAINT IF EXISTS fk_so_line_fulfill_location');
        $this->addSql('ALTER TABLE sales_order_line DROP COLUMN IF EXISTS pick_from_bin_location');
        $this->addSql('ALTER TABLE sales_order_line DROP COLUMN IF EXISTS fulfill_from_location_id');

        $this->addSql('ALTER TABLE sales_order DROP CONSTRAINT IF EXISTS fk_so_fulfill_from_location');
        $this->addSql('ALTER TABLE sales_order DROP COLUMN IF EXISTS fulfill_from_location_id');

        $this->addSql('ALTER TABLE item_receipt DROP CONSTRAINT IF EXISTS fk_receipt_location');
        $this->addSql('ALTER TABLE item_receipt RENAME COLUMN received_at_location_id TO received_at_location_id_old');
        $this->addSql('ALTER TABLE item_receipt ADD COLUMN received_at_location_id INTEGER');
        $this->addSql('UPDATE item_receipt SET received_at_location_id = received_at_location_id_old');
        $this->addSql('ALTER TABLE item_receipt DROP COLUMN received_at_location_id_old');

        $this->addSql('ALTER TABLE purchase_order_line DROP CONSTRAINT IF EXISTS fk_po_line_receiving_location');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN IF EXISTS receiving_bin_location');
        $this->addSql('ALTER TABLE purchase_order_line DROP COLUMN IF EXISTS receiving_location_id');

        $this->addSql('ALTER TABLE purchase_order DROP CONSTRAINT IF EXISTS fk_po_ship_to_location');
        $this->addSql('ALTER TABLE purchase_order RENAME COLUMN ship_to_location_id TO ship_to_location_id_old');
        $this->addSql('ALTER TABLE purchase_order ADD COLUMN ship_to_location_id INTEGER');
        $this->addSql('UPDATE purchase_order SET ship_to_location_id = ship_to_location_id_old');
        $this->addSql('ALTER TABLE purchase_order DROP COLUMN ship_to_location_id_old');
    }
}

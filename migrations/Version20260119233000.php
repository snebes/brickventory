<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NetSuite Sales Order and Item Fulfillment workflow schema updates:
 * - Add fulfillment_number, ship_method, tracking_number, shipping_cost, shipped_at to item_fulfillment
 * - Add quantity_committed, quantity_billed to sales_order_line  
 * - Create item_fulfillment_line table for line-level fulfillment tracking
 */
final class Version20260119233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'NetSuite Sales Order and Item Fulfillment workflow schema updates';
    }

    public function up(Schema $schema): void
    {
        // Update item_fulfillment table with new columns
        $this->addSql('ALTER TABLE item_fulfillment ADD COLUMN fulfillment_number VARCHAR(55) UNIQUE');
        $this->addSql('ALTER TABLE item_fulfillment ADD COLUMN ship_method VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_fulfillment ADD COLUMN tracking_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_fulfillment ADD COLUMN shipping_cost DECIMAL(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE item_fulfillment ADD COLUMN shipped_at TIMESTAMP DEFAULT NULL');
        
        // Update existing records with fulfillment numbers
        $this->addSql("UPDATE item_fulfillment SET fulfillment_number = 'IF-' || id || '-' || EXTRACT(EPOCH FROM fulfillment_date)::INTEGER WHERE fulfillment_number IS NULL");
        
        // Make fulfillment_number NOT NULL after populating existing records
        $this->addSql('ALTER TABLE item_fulfillment ALTER COLUMN fulfillment_number SET NOT NULL');

        // Update sales_order_line table with new quantity tracking columns
        $this->addSql('ALTER TABLE sales_order_line ADD COLUMN quantity_committed INTEGER DEFAULT 0');
        $this->addSql('ALTER TABLE sales_order_line ADD COLUMN quantity_billed INTEGER DEFAULT 0');
        
        // Set defaults for existing records
        $this->addSql('UPDATE sales_order_line SET quantity_committed = 0 WHERE quantity_committed IS NULL');
        $this->addSql('UPDATE sales_order_line SET quantity_billed = 0 WHERE quantity_billed IS NULL');
        
        // Make columns NOT NULL
        $this->addSql('ALTER TABLE sales_order_line ALTER COLUMN quantity_committed SET NOT NULL');
        $this->addSql('ALTER TABLE sales_order_line ALTER COLUMN quantity_billed SET NOT NULL');

        // Create item_fulfillment_line table for line-level fulfillment tracking
        $this->addSql('CREATE TABLE item_fulfillment_line (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_fulfillment_id INTEGER NOT NULL,
            sales_order_line_id INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            quantity_fulfilled INTEGER NOT NULL,
            serial_numbers JSON DEFAULT NULL,
            lot_number VARCHAR(100) DEFAULT NULL,
            bin_location VARCHAR(50) DEFAULT NULL,
            CONSTRAINT fk_ful_line_fulfillment FOREIGN KEY (item_fulfillment_id) REFERENCES item_fulfillment(id) ON DELETE CASCADE,
            CONSTRAINT fk_ful_line_so_line FOREIGN KEY (sales_order_line_id) REFERENCES sales_order_line(id) ON DELETE RESTRICT,
            CONSTRAINT fk_ful_line_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT
        )');

        // Create indexes for efficient querying
        $this->addSql('CREATE INDEX idx_ful_line_fulfillment ON item_fulfillment_line (item_fulfillment_id)');
        $this->addSql('CREATE INDEX idx_ful_line_so_line ON item_fulfillment_line (sales_order_line_id)');
        $this->addSql('CREATE INDEX idx_ful_line_item ON item_fulfillment_line (item_id)');
        $this->addSql('CREATE INDEX idx_fulfillment_tracking ON item_fulfillment (tracking_number)');
        $this->addSql('CREATE INDEX idx_fulfillment_status ON item_fulfillment (status)');
    }

    public function down(Schema $schema): void
    {
        // Drop item_fulfillment_line table and indexes
        $this->addSql('DROP INDEX IF EXISTS idx_ful_line_fulfillment');
        $this->addSql('DROP INDEX IF EXISTS idx_ful_line_so_line');
        $this->addSql('DROP INDEX IF EXISTS idx_ful_line_item');
        $this->addSql('DROP INDEX IF EXISTS idx_fulfillment_tracking');
        $this->addSql('DROP INDEX IF EXISTS idx_fulfillment_status');
        $this->addSql('DROP TABLE item_fulfillment_line');

        // Remove columns from sales_order_line
        $this->addSql('ALTER TABLE sales_order_line DROP COLUMN quantity_committed');
        $this->addSql('ALTER TABLE sales_order_line DROP COLUMN quantity_billed');

        // Remove columns from item_fulfillment
        $this->addSql('ALTER TABLE item_fulfillment DROP COLUMN fulfillment_number');
        $this->addSql('ALTER TABLE item_fulfillment DROP COLUMN ship_method');
        $this->addSql('ALTER TABLE item_fulfillment DROP COLUMN tracking_number');
        $this->addSql('ALTER TABLE item_fulfillment DROP COLUMN shipping_cost');
        $this->addSql('ALTER TABLE item_fulfillment DROP COLUMN shipped_at');
    }
}

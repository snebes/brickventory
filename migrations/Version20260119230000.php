<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add cost_layer table for FIFO inventory costing and add unit_cost to item_receipt_line
 */
final class Version20260119230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cost_layer table for FIFO inventory costing and add unit_cost column to item_receipt_line';
    }

    public function up(Schema $schema): void
    {
        // Create cost_layer table for FIFO inventory valuation
        $this->addSql('CREATE TABLE cost_layer (
            id SERIAL PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            item_id INTEGER NOT NULL,
            item_receipt_line_id INTEGER DEFAULT NULL,
            quantity_received INTEGER NOT NULL,
            quantity_remaining INTEGER NOT NULL,
            unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            receipt_date TIMESTAMP NOT NULL,
            CONSTRAINT fk_cost_layer_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE RESTRICT,
            CONSTRAINT fk_cost_layer_receipt_line FOREIGN KEY (item_receipt_line_id) REFERENCES item_receipt_line(id) ON DELETE SET NULL
        )');

        // Create indexes for efficient FIFO queries
        $this->addSql('CREATE INDEX idx_cost_layer_item_date ON cost_layer (item_id, receipt_date)');
        $this->addSql('CREATE INDEX idx_cost_layer_receipt_line ON cost_layer (item_receipt_line_id)');

        // Add unit_cost column to item_receipt_line for cost tracking
        $this->addSql('ALTER TABLE item_receipt_line ADD COLUMN unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00');
    }

    public function down(Schema $schema): void
    {
        // Remove unit_cost column from item_receipt_line
        $this->addSql('ALTER TABLE item_receipt_line DROP COLUMN unit_cost');

        // Drop cost_layer table
        $this->addSql('DROP TABLE cost_layer');
    }
}
